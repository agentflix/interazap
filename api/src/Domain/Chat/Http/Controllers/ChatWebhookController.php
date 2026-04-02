<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatUazapiWebhookActions;
use Domain\Chat\Http\Requests\ChatUazapiWebhookRequest;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Controlador de Webhooks de Chat.
 *
 * Ponto de entrada (Ingestor) para notificações enviadas pelos provedores externos.
 * Valida o token da instância e orquestra o processamento assíncrono ou imediato
 * dos eventos (mensagens, status de entrega, presença).
 *
 * @category Controllers
 */
final class ChatWebhookController extends BaseController
{
    public function __construct(
        private readonly ChatUazapiWebhookActions $actions,
        private readonly MetricsService $metricsService,
    ) {}

    /**
     * Receber e processar eventos vindos do provedor Uazapi.
     *
     * @param  ChatUazapiWebhookRequest  $request  Carga de dados validada inicialmente.
     * @param  string  $token  Token de segurança da instância para identificação do tenant.
     * @return JsonResponse Confirmação de recebimento para o gateway.
     */
    public function uazapi(ChatUazapiWebhookRequest $request, string $token): JsonResponse
    {
        $startedAt = microtime(true);
        $status = 'success';

        // 1. Log Integral (antes de qualquer validação adicional)
        Log::channel('webhook')->info('Webhook recebido', [
            'provider' => 'uazapi',
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'payload_keys' => array_keys($request->all()),
            'event_type' => $request->input('EventType') ?? $request->input('event_type'),
            'instance_token' => $this->maskToken($token),
            'ip' => $request->ip(),
        ]);

        try {
            $request->validated();
            $result = $this->actions->handle($token, $request->validated());

            return $this->success($result, 'Webhook recebido');
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Token de webhook inválido') {
                $status = 'unauthorized';

                return $this->unauthorized($e->getMessage());
            }

            $status = 'error';
            report($e);

            return $this->error('Erro ao processar webhook', 500);
        } catch (\Throwable $e) {
            $status = 'error';
            report($e);

            return $this->error('Erro ao processar webhook', 500);
        } finally {
            $this->metricsService->recordAutopilotWebhookDuration(
                microtime(true) - $startedAt,
                [
                    'provider' => 'uazapi',
                    'status' => $status,
                ]
            );
        }
    }

    /**
     * Masks the token for logs.
     *
     * @param  string  $token  Original token.
     * @return string Masked token.
     */
    private function maskToken(string $token): string
    {
        $suffix = substr($token, -8);

        return $suffix ? '****-'.$suffix : '****';
    }
}
