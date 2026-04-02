<?php

declare(strict_types=1);

namespace Domain\Chat\Actions\WebhookHandlers;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Services\ChatBroadcastService;

/**
 * Handler para deleção de mensagens.
 */
final class ChatWebhookDeleteHandler implements ChatWebhookHandlerInterface
{
    public function __construct(private readonly ChatBroadcastService $broadcastService) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function supports(string $eventType, array $payload): bool
    {
        return $eventType === 'message.delete' || $eventType === 'messages.delete';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $tenantId, array $payload): void
    {
        $messageId = data_get($payload, 'message.id')
            ?? data_get($payload, 'raw.key.id')
            ?? data_get($payload, 'message_id');

        if (! $messageId) {
            logger()->warning('[ChatWebhookDeleteHandler] Delete event without message ID', $payload);

            return;
        }

        $message = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('external_id', $messageId)
            ->first();

        if (! $message) {
            logger()->debug('[ChatWebhookDeleteHandler] Message not found for delete', ['external_id' => $messageId]);

            return;
        }

        $ticketId = $message->ticket_id;

        // Soft delete - marcar como deletada
        $message->is_deleted = true;
        $message->deleted_at = now();
        $message->status = 'deleted';

        $metadata = $message->metadata ?? [];
        $metadata['deleted_by'] = 'remote';
        $message->metadata = $metadata;
        $message->save();

        // Broadcast da deleção
        $this->broadcastService->emitMessageDelete([
            'message_id' => (string) $message->id,
            'ticket_id' => (string) $ticketId,
            'tenant_id' => $tenantId,
        ]);

        logger()->debug('[ChatWebhookDeleteHandler] Message deleted', ['message_id' => $message->id]);
    }
}
