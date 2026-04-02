<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\ChatGatewayService;

/**
 * Casos de Uso para Exclusão de Mensagens.
 *
 * Responsável por excluir mensagens no gateway e persistir estado local.
 *
 * @category Actions
 */
final class ChatMessageDeleteActions
{
    public function __construct(
        private readonly ChatGatewayService $gateway,
        private readonly ChatBroadcastService $broadcastService,
    ) {}

    /**
     * Excluir uma mensagem para todos.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $messageId  ID da mensagem no sistema.
     * @param  string|null  $deletedByUserId  Identificador do usuário que solicitou exclusão.
     * @return array<string, mixed> Resultado da operação.
     */
    public function delete(string $tenantId, string $messageId, ?string $deletedByUserId = null): array
    {
        $message = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $messageId)
            ->where('direction', 'outgoing')
            ->firstOrFail();

        $ticket = $message->ticket;
        if (! $ticket instanceof ChatTicket) {
            throw new \RuntimeException('Ticket not found for message');
        }

        $instance = $ticket->instance;
        $token = $instance?->webhook_token;

        if (! $token || ! $message->external_id) {
            return ['success' => false, 'error' => 'Missing token or external_id'];
        }

        $response = $this->gateway->deleteMessage($token, [
            'id' => $message->external_id,
        ]);

        $message->status = 'deleted';
        $message->is_deleted = true;
        $message->deleted_at = now();
        $message->deleted_by = $deletedByUserId;
        $message->save();

        $this->broadcastService->emitMessageDelete([
            'message_id' => (string) $message->id,
            'ticket_id' => (string) $message->ticket_id,
            'tenant_id' => $tenantId,
            'status' => 'deleted',
            'deleted_at' => $message->deleted_at->toIso8601String(),
            'deleted_by' => $message->deleted_by,
        ]);

        return ['success' => true, 'response' => $response];
    }
}
