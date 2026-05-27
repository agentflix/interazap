<?php

declare(strict_types=1);

namespace Domain\Chat\Actions\WebhookHandlers;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Services\ChatBroadcastService;

/**
 * Handler para eventos de reação em mensagens via webhook.
 *
 * Suporta detecção por tipo de evento e por conteúdo do payload (campo reaction).
 * Emoji vazio remove a reação existente do remetente. Emite evento WebSocket de reação.
 */
final class ChatWebhookReactionHandler implements ChatWebhookHandlerInterface
{
    public function __construct(private readonly ChatBroadcastService $broadcastService) {}

    /**
     * Suporta eventos de reação por tipo ('messages.reaction', 'message.reaction'),
     * por tipo de mensagem no payload ('reaction', 'reactionmessage') ou pela
     * presença do campo 'reaction' no payload.
     *
     * @param  string  $eventType  Tipo do evento recebido.
     * @param  array<string, mixed>  $payload  Payload bruto do webhook.
     * @return bool True para eventos de reação.
     */
    public function supports(string $eventType, array $payload): bool
    {
        $normalizedEventType = strtolower(trim($eventType));
        if ($normalizedEventType === 'messages.reaction' || $normalizedEventType === 'message.reaction') {
            return true;
        }

        $messageType = strtolower(trim((string) (
            data_get($payload, 'message.type')
            ?? data_get($payload, 'message.messageType')
            ?? data_get($payload, 'message.mediaType')
            ?? data_get($payload, 'raw.message.type')
            ?? data_get($payload, 'raw.message.messageType')
            ?? ''
        )));

        if ($messageType === 'reaction' || $messageType === 'reactionmessage') {
            return true;
        }

        return $this->hasReactionContent(data_get($payload, 'message.reaction'))
            || $this->hasReactionContent(data_get($payload, 'raw.message.reaction'));
    }

    /**
     * Considera reação apenas valores não-vazios. Uazapi inclui `reaction: ""`
     * em toda mensagem comum, então strings vazias e arrays vazios devem ser ignorados.
     */
    private function hasReactionContent(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /**
     * Processa o evento de reação, atualizando as reações da mensagem e emitindo broadcast.
     *
     * Opera silenciosamente se não encontrar message_id ou mensagem no banco.
     * Emoji vazio remove reação existente do mesmo remetente (from_me).
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $payload  Payload bruto do evento de reação.
     */
    public function handle(string $tenantId, array $payload): void
    {
        $messageIdCandidates = [
            data_get($payload, 'message.reaction'),
            data_get($payload, 'message.content.key.ID'),
            data_get($payload, 'message.content.key.id'),
            data_get($payload, 'raw.message.reaction'),
            data_get($payload, 'raw.message.content.key.ID'),
            data_get($payload, 'raw.message.content.key.id'),
            data_get($payload, 'message.id'),
            data_get($payload, 'raw.key.id'),
            data_get($payload, 'message_id'),
        ];

        $messageId = null;
        foreach ($messageIdCandidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $messageId = trim($candidate);
                break;
            }
        }

        $emoji = data_get($payload, 'message.reaction.emoji')
            ?? data_get($payload, 'raw.reaction.text')
            ?? data_get($payload, 'message.text')
            ?? data_get($payload, 'message.content.text')
            ?? data_get($payload, 'raw.message.text')
            ?? data_get($payload, 'raw.message.content.text')
            ?? data_get($payload, 'emoji');

        $fromMe = (bool) (data_get($payload, 'message.fromMe')
            ?? data_get($payload, 'message.content.key.fromMe')
            ?? data_get($payload, 'raw.message.fromMe')
            ?? data_get($payload, 'raw.message.content.key.fromMe')
            ?? data_get($payload, 'raw.key.fromMe', false));

        if (! $messageId) {
            logger()->warning('[ChatWebhookReactionHandler] Reaction event without message ID', $payload);

            return;
        }

        $message = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('external_id', $messageId)
            ->first();

        if (! $message) {
            logger()->debug('[ChatWebhookReactionHandler] Message not found for reaction', ['external_id' => $messageId]);

            return;
        }

        $message->loadMissing('extended');

        $reactions = $message->reactions ?? [];

        // Emoji vazio remove a reação
        if (empty($emoji)) {
            $reactions = array_filter($reactions, fn ($r) => $r['from_me'] !== $fromMe);
        } else {
            // Substituir reação existente do mesmo remetente ou adicionar nova
            $found = false;
            foreach ($reactions as &$reaction) {
                if ($reaction['from_me'] === $fromMe) {
                    $reaction['emoji'] = $emoji;
                    $reaction['timestamp'] = now()->toIso8601String();
                    $found = true;
                    break;
                }
            }
            unset($reaction);
            if (! $found) {
                $reactions[] = [
                    'emoji' => $emoji,
                    'from_me' => $fromMe,
                    'timestamp' => now()->toIso8601String(),
                ];
            }
        }

        $message->reactions = array_values($reactions);
        $message->save();

        // Broadcast da reação
        $this->broadcastService->emitMessageReaction([
            'message_id' => (string) $message->id,
            'ticket_id' => (string) $message->ticket_id,
            'tenant_id' => $tenantId,
            'emoji' => $emoji,
            'from_me' => $fromMe,
            'reactions' => $message->reactions ?? [],
        ]);

        logger()->debug('[ChatWebhookReactionHandler] Reaction processed', [
            'message_id' => $message->id,
            'emoji' => $emoji,
        ]);
    }
}
