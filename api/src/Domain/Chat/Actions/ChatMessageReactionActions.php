<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Illuminate\Support\Str;

/**
 * Casos de Uso para Reações de Mensagens.
 *
 * Responsável por enviar reações ao gateway e persistir estado local.
 *
 * @category Actions
 */
final class ChatMessageReactionActions
{
    public function __construct(
        private readonly ChatGatewayService $gateway,
        private readonly ChatBroadcastService $broadcastService,
    ) {}

    /**
     * Reagir a uma mensagem com emoji.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $messageId  ID da mensagem no sistema.
     * @param  string  $emoji  Emoji Unicode da reação (vazio para remover).
     * @return array<string, mixed> Resultado da operação.
     */
    public function react(string $tenantId, string $messageId, string $emoji): array
    {
        $message = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $messageId)
            ->firstOrFail();

        $message->loadMissing('extended');

        $ticket = $message->ticket;
        if (! $ticket instanceof ChatTicket) {
            throw new \RuntimeException('Ticket not found for message');
        }

        $instance = $ticket->instance;
        $token = $instance?->webhook_token;
        $number = $ticket->remote_jid ?? $ticket->phone_e164 ?? $ticket->phone;

        if (! $token || ! $number || ! $message->external_id) {
            return ['success' => false, 'error' => 'Missing token, number or external_id'];
        }

        $cleanNumber = preg_replace('/[^0-9]/', '', Str::before($number, '@'));

        $response = $this->gateway->reactToMessage($token, [
            'number' => $cleanNumber,
            'id' => $message->external_id,
            'text' => $emoji,
        ]);

        $reactions = $message->reactions ?? [];
        if ($emoji === '') {
            $reactions = array_filter($reactions, fn (array $reaction) => ($reaction['from_me'] ?? false) !== true);
        } else {
            $updated = false;
            foreach ($reactions as &$reaction) {
                if (($reaction['from_me'] ?? false) === true) {
                    $reaction['emoji'] = $emoji;
                    $reaction['timestamp'] = now()->toIso8601String();
                    $updated = true;
                    break;
                }
            }
            unset($reaction);

            if (! $updated) {
                $reactions[] = [
                    'emoji' => $emoji,
                    'from_me' => true,
                    'timestamp' => now()->toIso8601String(),
                ];
            }
        }

        $message->reactions = array_values($reactions);
        $message->save();

        $this->broadcastService->emitMessageReaction([
            'message_id' => (string) $message->id,
            'ticket_id' => (string) $message->ticket_id,
            'tenant_id' => $tenantId,
            'emoji' => $emoji,
            'from_me' => true,
            'reactions' => $message->reactions ?? [],
        ]);

        return ['success' => true, 'response' => $response];
    }
}
