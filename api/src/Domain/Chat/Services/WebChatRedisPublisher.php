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
class WebChatRedisPublisher
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
                event: 'webchat:ai_response',
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

    /**
     * Publicar mensagem do atendente para o cliente webchat.
     *
     * @param  string  $sessionId  ID da sessão webchat.
     * @param  string  $tenantId  ID do tenant.
     * @param  array<string, mixed>  $message  Payload da mensagem do atendente.
     */
    public function publishAgentMessage(string $sessionId, string $tenantId, array $message): void
    {
        $room = 'session:'.$sessionId;

        try {
            $this->broadcastService->broadcastEvent(
                event: 'webchat:agent_message',
                data: [
                    'tenant_id' => $tenantId,
                    'session_id' => $sessionId,
                    'message' => $message,
                ],
                room: $room,
            );

            Log::debug('[WebChatRedisPublisher] Agent message published', [
                'session_id' => $sessionId,
                'tenant_id' => $tenantId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WebChatRedisPublisher] Failed to publish agent message', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Publicar evento de encerramento do ticket para o cliente webchat.
     *
     * Notifica o cliente conectado na sessão que o atendente encerrou o chamado,
     * permitindo que a UI atualize o estado e ofereça opção de novo chamado.
     *
     * @param  string  $sessionId  ID da sessão webchat.
     * @param  string  $tenantId  ID do tenant.
     * @param  string  $ticketId  ID do ticket encerrado.
     */
    public function publishTicketClosed(string $sessionId, string $tenantId, string $ticketId): void
    {
        $room = 'session:'.$sessionId;

        try {
            $this->broadcastService->broadcastEvent(
                event: 'webchat:ticket_closed',
                data: [
                    'tenant_id' => $tenantId,
                    'session_id' => $sessionId,
                    'ticket_id' => $ticketId,
                ],
                room: $room,
            );

            Log::debug('[WebChatRedisPublisher] Ticket closed published', [
                'session_id' => $sessionId,
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WebChatRedisPublisher] Failed to publish ticket closed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
