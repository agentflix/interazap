<?php

declare(strict_types=1);

namespace Domain\Chat\Listeners;

use Domain\Ai\Events\AiResponseReceived;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Services\WebChatRedisPublisher;
use Illuminate\Support\Facades\Log;

/**
 * Listener para responder eventos de resposta da IA.
 *
 * Quando a IA salva uma resposta (source='ai', direction='outgoing'),
 * este listener publica no Redis ws.events para notificar o Gateway.
 *
 * @category Listeners
 */
final class AiResponseListener
{
    public function __construct(
        private readonly WebChatRedisPublisher $redisPublisher,
    ) {}

    /**
     * Processa o evento de resposta da IA e publica a mensagem no Redis para o Gateway webchat.
     */
    public function handle(AiResponseReceived $event): void
    {
        $tenantId = $event->tenantId;
        $ticketId = $event->ticketId;
        $messageId = $event->messageId ?? null;
        $sessionId = $event->context['session_id'] ?? null;

        // Apenas publicar se vier de contexto webchat e tiver session_id
        if (! $sessionId) {
            return;
        }

        $message = null;
        if ($messageId) {
            $message = ChatMessage::query()
                ->where('id', $messageId)
                ->where('tenant_id', $tenantId)
                ->with('extended')
                ->first();
        }

        if (! $message) {
            Log::warning('[AiResponseListener] Message not found for AI response', [
                'message_id' => $messageId,
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketId,
            ]);

            return;
        }

        $this->redisPublisher->publishAiResponse(
            sessionId: (string) $sessionId,
            message: [
                'id' => (string) $message->id,
                'ticket_id' => (string) $message->ticket_id,
                'tenant_id' => (string) $message->tenant_id,
                'content' => $message->content,
                'source' => $message->source,
                'direction' => $message->direction,
                'type' => $message->type,
                'file_url' => $message->file_url,
                'file_name' => $message->file_name,
                'mime_type' => $message->mime_type,
                'file_size' => $message->file_size,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        );

        Log::debug('[AiResponseListener] AI response published to Redis', [
            'message_id' => (string) $message->id,
            'session_id' => $sessionId,
            'tenant_id' => $tenantId,
        ]);
    }
}
