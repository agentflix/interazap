<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Support\Facades\Log;

/**
 * Redis publisher for WebChat real-time events.
 *
 * Publica eventos de resposta da IA no canal ws.events do Redis,
 * permitindo que o Gateway NestJS distribua aos clientes webchat
 * via Socket.io.
 *
 * @category Services
 */
final class WebChatRedisPublisher
{
    public function __construct(
        private readonly GatewayBroadcastService $broadcastService,
    ) {}

    /**
     * Publicar resposta da IA para o cliente webchat.
     *
     * @param  string  $sessionId  ID da sessão webchat.
     * @param  array<string, mixed>  $message  Payload da mensagem de resposta.
     */
    public function publishAiResponse(string $sessionId, array $message): void
    {
        $tenantId = $message['tenant_id'] ?? '';
        $room = 'session:'.$sessionId;

        try {
            $this->broadcastService->broadcastEvent(
                event: 'webchat.ai_response',
                data: [
                    'tenant_id' => $tenantId,
                    'session_id' => $sessionId,
                    'message' => $message,
                ],
                room: $room,
            );

            Log::debug('[WebChatRedisPublisher] AI response published', [
                'session_id' => $sessionId,
                'tenant_id' => $tenantId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WebChatRedisPublisher] Failed to publish AI response', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
