<?php

declare(strict_types=1);

namespace Domain\Chat\Actions\WebhookHandlers;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Services\ChatBroadcastService;

/**
 * Handler para eventos de deleção de mensagens via webhook.
 *
 * Realiza soft delete da mensagem no banco, marca como deletada por remetente
 * remoto e emite evento WebSocket de deleção via ChatBroadcastService.
 */
final class ChatWebhookDeleteHandler implements ChatWebhookHandlerInterface
{
    public function __construct(private readonly ChatBroadcastService $broadcastService) {}

    /**
     * Suporta eventos 'message.delete' e 'messages.delete'.
     *
     * @param  string  $eventType  Tipo do evento recebido.
     * @param  array<string, mixed>  $payload  Payload bruto do webhook.
     * @return bool True para eventos de deleção.
     */
    public function supports(string $eventType, array $payload): bool
    {
        return $eventType === 'message.delete' || $eventType === 'messages.delete';
    }

    /**
     * Processa o evento de deleção, marcando a mensagem como deletada e emitindo broadcast.
     *
     * Opera silenciosamente se o message_id não for encontrado no payload ou
     * se a mensagem não existir no banco para o tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $payload  Payload bruto do evento de deleção.
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
