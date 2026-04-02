<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Controllers;

use Domain\Platform\Http\Requests\PlatformUazapiSendFileRequest;
use Domain\Platform\Http\Requests\PlatformUazapiSendTextRequest;
use Domain\Platform\Models\PlatformUazapiInstance;
use Domain\Platform\Services\UazapiGatewayService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Controlador Proxy para Envio de Mensagens via Uazapi.
 *
 * Atua como uma ponte entre o frontend e o Gateway de Integração,
 * validando as solicitações de envio de texto e arquivos.
 *
 * @category Controllers
 */
final class PlatformUazapiMessageController extends BaseController
{
    /**
     * @param  UazapiGatewayService  $gateway  Serviço de gateway Uazapi.
     */
    public function __construct(private readonly UazapiGatewayService $gateway) {}

    /**
     * Enviar uma mensagem de texto simples.
     *
     * @param  PlatformUazapiSendTextRequest  $request  Dados validados (destinatário, corpo).
     * @param  string  $token  Token identificador da instância WhatsApp.
     * @return JsonResponse Resposta de sucesso do gateway.
     */
    public function sendText(PlatformUazapiSendTextRequest $request, string $token): JsonResponse
    {
        $instanceToken = $this->resolveAuthorizedInstanceToken($token);

        $response = $this->gateway->sendText($instanceToken, $request->validated());

        return $this->success($response, 'Mensagem enviada');
    }

    /**
     * Enviar um arquivo ou mídia (documento, imagem, áudio).
     *
     * @param  PlatformUazapiSendFileRequest  $request  Dados validados (destinatário, URL/Base64).
     * @param  string  $token  Token identificador da instância WhatsApp.
     * @return JsonResponse Resposta de sucesso do gateway.
     */
    public function sendFile(PlatformUazapiSendFileRequest $request, string $token): JsonResponse
    {
        $instanceToken = $this->resolveAuthorizedInstanceToken($token);

        $response = $this->gateway->sendFile($instanceToken, $request->validated());

        return $this->success($response, 'Arquivo enviado');
    }

    private function resolveAuthorizedInstanceToken(string $token): string
    {
        $instance = PlatformUazapiInstance::query()
            ->where('token', $token)
            ->firstOrFail();

        $this->authorize('view', $instance);

        return $instance->token;
    }
}
