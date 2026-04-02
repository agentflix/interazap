<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\ChatGatewayService;

/**
 * Casos de Uso para Edição de Mensagens.
 *
 * Responsável por editar mensagens enviadas e persistir histórico local.
 *
 * @category Actions
 */
final class ChatMessageEditActions
{
    public function __construct(
        private readonly ChatGatewayService $gateway,
        private readonly ChatBroadcastService $broadcastService,
    ) {}

    /**
     * Editar uma mensagem enviada.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $messageId  ID da mensagem no sistema.
     * @param  string  $newText  Novo conteúdo da mensagem.
     * @return array<string, mixed> Resultado da operação.
     */
    public function edit(string $tenantId, string $messageId, string $newText): array
    {
        $message = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $messageId)
            ->where('direction', 'outgoing')
            ->firstOrFail();

        $message->loadMissing('extended');

        $ticket = $message->ticket;
        if (! $ticket instanceof ChatTicket) {
            throw new \RuntimeException('Ticket not found for message');
        }

        $instance = $ticket->instance;
        $token = $instance?->webhook_token;

        if (! $token || ! $message->external_id) {
            return ['success' => false, 'error' => 'Missing token or external_id'];
        }

        $response = $this->gateway->editMessage($token, [
            'id' => $message->external_id,
            'text' => $newText,
        ]);

        $history = $message->edit_history ?? [];
        $history[] = [
            'content' => $message->content,
            'edited_at' => now()->toIso8601String(),
        ];

        $message->content = $newText;
        $message->is_edited = true;
        $message->edited_at = now();
        $message->edit_history = $history;
        $message->save();

        $this->broadcastService->emitMessageEdit([
            'message_id' => (string) $message->id,
            'ticket_id' => (string) $message->ticket_id,
            'tenant_id' => $tenantId,
            'content' => $newText,
            'is_edited' => true,
        ]);

        return ['success' => true, 'response' => $response];
    }
}
