<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketEvaluation;
use Domain\Chat\Services\ChatGatewayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Criar e enviar solicitação de avaliação (CSAT) ao final da conversa.
 *
 * Responsabilidades:
 * - Verificar habilitação de avaliações na instância
 * - Gerar token de avaliação único
 * - Construir URL de avaliação
 * - Enviar link via gateway
 * - Registrar status de envio
 */
final class EvaluateTicketCsatAction
{
    public function __construct(
        private readonly ChatGatewayService $gateway,
    ) {}

    /**
     * Cria e envia solicitação de avaliação CSAT ao encerrar o ticket.
     *
     * Cria ou atualiza ChatTicketEvaluation com token único, constrói URL pública
     * de avaliação e envia mensagem via gateway. Registra falha no log sem lançar exceção.
     * Opera silenciosamente se a instância não tiver evaluation_enabled ou se o número
     * de destino não estiver disponível.
     *
     * @param  ChatTicket  $ticket  Ticket encerrado para avaliação.
     */
    public function evaluate(ChatTicket $ticket): void
    {
        if (! $ticket->instance_id) {
            return;
        }

        $instance = ChatInstance::query()
            ->select(['id', 'tenant_id', 'provider', 'webhook_token', 'settings_json', 'evaluation_enabled'])
            ->where('tenant_id', $ticket->tenant_id)
            ->find($ticket->instance_id);

        if (! $instance || ! $instance->evaluation_enabled) {
            return;
        }

        $evaluation = ChatTicketEvaluation::query()->updateOrCreate(
            ['ticket_id' => $ticket->id, 'tenant_id' => $ticket->tenant_id],
            [
                'token' => (string) Str::orderedUuid(),
                'rating' => 0,
                'comment' => null,
                'submitted_at' => null,
            ]
        );

        $phone = $this->resolvePhone($ticket);
        $token = $this->resolveGatewayToken($instance);

        if (! $phone || ! $token) {
            return;
        }

        $evaluationBaseUrl = (string) config('chat.evaluation_public_url', 'http://localhost:4200');
        $evaluationUrl = rtrim($evaluationBaseUrl, '/').'/chat/evaluations/'.$evaluation->token;
        $message = 'Olá! Seu atendimento foi encerrado. Gostaríamos da sua avaliação: '.$evaluationUrl;

        $chatMessage = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $ticket->tenant_id,
            'ticket_id' => (string) $ticket->id,
            'user_id' => $ticket->assigned_to,
            'contact_id' => $ticket->contact_id,
            'content' => $message,
            'type' => 'text',
            'direction' => 'outgoing',
            'is_from_contact' => false,
            'source' => 'system',
            'status' => 'pending',
            'metadata' => [
                'kind' => 'evaluation_invitation',
                'evaluation_id' => (string) $evaluation->id,
                'evaluation_token' => (string) $evaluation->token,
                'evaluation_url' => $evaluationUrl,
            ],
        ]);

        try {
            $response = $this->gateway->sendText((string) $token, [
                'number' => $phone,
                'text' => $message,
            ]);

            $chatMessage->status = 'sent';
            $chatMessage->sent_at = now();
            $chatMessage->external_id = $response['messageid'] ?? $response['id'] ?? null;
            $chatMessage->save();
        } catch (\Throwable $exception) {
            $chatMessage->status = 'failed';
            $chatMessage->error_message = $exception->getMessage();
            $chatMessage->save();

            Log::warning('chat.evaluation_link_send_failed', [
                'tenant_id' => (string) $ticket->tenant_id,
                'ticket_id' => (string) $ticket->id,
                'instance_id' => (string) $instance->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Resolve o número de telefone do contato do ticket em formato numérico puro.
     *
     * @param  ChatTicket  $ticket  Ticket com dados de contato.
     * @return string|null Número limpo ou null se não disponível.
     */
    private function resolvePhone(ChatTicket $ticket): ?string
    {
        $number = $ticket->phone_e164 ?? $ticket->phone ?? $ticket->remote_jid;

        if (! $number && $ticket->contact) {
            $number = $ticket->contact->whatsapp ?? $ticket->contact->phone;
        }

        if (! is_string($number) || $number === '') {
            return null;
        }

        $normalized = str_contains($number, '@') ? Str::before($number, '@') : $number;
        $digits = (string) preg_replace('/[^0-9]/', '', $normalized);

        return $digits !== '' ? $digits : null;
    }

    /**
     * Resolve o token de autenticação do gateway para a instância.
     *
     * @param  ChatInstance  $instance  Instância de chat.
     * @return string|null Token ou null se não configurado.
     */
    private function resolveGatewayToken(ChatInstance $instance): ?string
    {
        $settingsToken = is_array($instance->settings_json) ? $instance->settings_json['token'] ?? null : null;

        if (is_string($settingsToken) && $settingsToken !== '') {
            return $settingsToken;
        }

        return $instance->webhook_token !== '' ? $instance->webhook_token : null;
    }
}
