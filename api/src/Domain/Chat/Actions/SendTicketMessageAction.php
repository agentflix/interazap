<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatGatewayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Enviar mensagens de sistema configuradas para tickets.
 *
 * Responsabilidades:
 * - Enviar mensagens preconfiguradas (templates)
 * - Resolver número de telefone e token do gateway
 * - Registrar status de envio (sent/failed)
 * - Integrar com gateway externo
 */
final class SendTicketMessageAction
{
    public function __construct(
        private readonly ChatGatewayService $gateway,
    ) {}

    /**
     * Enviar mensagem automatizada configurada para o ticket.
     *
     * @param  string  $flagKey  Chave de habilitação em instance.settings_json
     * @param  string  $messageKey  Chave do conteúdo em instance.settings_json
     * @param  string  $kind  Identificador técnico (evaluation_invitation, etc)
     * @param  array<string, mixed>  $extraMetadata  Metadados adicionais
     */
    public function sendConfiguredSystemMessage(
        ChatTicket $ticket,
        string $flagKey,
        string $messageKey,
        string $kind,
        array $extraMetadata = []
    ): void {
        if (! $ticket->instance_id) {
            return;
        }

        $instance = ChatInstance::query()
            ->select(['id', 'tenant_id', 'provider', 'mode', 'webhook_token', 'settings_json'])
            ->where('tenant_id', $ticket->tenant_id)
            ->find($ticket->instance_id);

        if (! $instance) {
            return;
        }

        $settings = is_array($instance->settings_json) ? $instance->settings_json : [];
        $enabled = (bool) ($settings[$flagKey] ?? false);
        $message = trim((string) ($settings[$messageKey] ?? ''));

        if (! $enabled || $message === '') {
            return;
        }

        $phone = $this->resolvePhone($ticket);
        $token = $this->resolveGatewayToken($instance);

        if (! $phone || ! $token) {
            return;
        }

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
                'kind' => $kind,
                ...$extraMetadata,
            ],
        ]);

        try {
            if ($instance->provider === 'zapi') {
                $response = $this->gateway->sendOutboundMessage(
                    'zapi',
                    (string) $token,
                    (string) $ticket->tenant_id,
                    (string) $instance->id,
                    [
                        'type' => 'text',
                        'to' => $phone,
                        'text' => $message,
                    ]
                );
            } else {
                $response = $this->gateway->sendText((string) $token, [
                    'number' => $phone,
                    'text' => $message,
                ]);
            }

            $chatMessage->status = 'sent';
            $chatMessage->sent_at = now();
            $chatMessage->external_id = $response['messageid'] ?? $response['id'] ?? null;
            $chatMessage->metadata = array_merge($chatMessage->metadata ?? [], ['gateway_response' => $response]);
            $chatMessage->save();
        } catch (\Throwable $exception) {
            $chatMessage->status = 'failed';
            $chatMessage->error_message = $exception->getMessage();
            $chatMessage->save();

            Log::warning('chat.automated_message_send_failed', [
                'tenant_id' => (string) $ticket->tenant_id,
                'ticket_id' => (string) $ticket->id,
                'instance_id' => (string) $instance->id,
                'kind' => $kind,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Resolver número de telefone do contato.
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
     * Resolver token do gateway.
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
