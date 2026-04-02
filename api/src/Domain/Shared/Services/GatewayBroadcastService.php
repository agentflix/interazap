<?php

declare(strict_types=1);

namespace Domain\Shared\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Serviço de Broadcast via Gateway WebSocket.
 *
 * Responsável por enviar eventos em tempo real ao Gateway NestJS,
 * que distribui via Socket.io aos clientes conectados.
 *
 * @category Services
 */
final class GatewayBroadcastService
{
    private string $gatewayUrl;

    private bool $realtimePubSubEnabled;

    private bool $realtimeHttpFallbackEnabled;

    /**
     * Configura o serviço de broadcast via Gateway.
     *
     * @note Resolved from config:
     *   - services.gateway.url                          → base URL do gateway (default: 'http://gateway:3000')
     *   - services.gateway.realtime_pubsub_enabled      → habilita publish via Redis PubSub (default: true)
     *   - services.gateway.realtime_http_fallback_enabled → habilita fallback HTTP (default: true)
     *   - services.gateway.api_key                      → chave de autenticação para fallback HTTP
     *   - services.gateway.timeout                      → timeout do fallback HTTP em segundos (default: 3)
     */
    public function __construct()
    {
        $this->gatewayUrl = config('services.gateway.url', 'http://gateway:3000');
        $this->realtimePubSubEnabled = (bool) config('services.gateway.realtime_pubsub_enabled', true);
        $this->realtimeHttpFallbackEnabled = (bool) config('services.gateway.realtime_http_fallback_enabled', true);
    }

    /**
     * Emitir evento genérico para uma room específica ou tenant.
     *
     * @param  string  $event  Nome do evento (ex: 'chat.message.status').
     * @param  array<string, mixed>  $data  Payload de dados do evento.
     * @param  string|null  $room  Sala de destino (opcional, ex: 'tenant:123').
     */
    public function broadcastEvent(string $event, array $data, ?string $room = null): void
    {
        // Validate tenant isolation
        $this->validateTenantIsolation($data);

        $tenantId = (string) ($data['tenant_id'] ?? $data['data']['tenant_id'] ?? '');
        $rooms = $this->resolveRooms($tenantId, $room);

        if ($this->realtimePubSubEnabled) {
            $published = $this->publishToRedis($event, $tenantId, $rooms, $data);
            if ($published) {
                return;
            }
        }

        if (! $this->realtimeHttpFallbackEnabled) {
            return;
        }

        foreach ($rooms as $targetRoom) {
            $this->sendHttpEvent($event, $data, $targetRoom);
        }
    }

    /**
     * Emitir envelope de status de mensagem.
     *
     * @param  array{
     *   message_id: string,
     *   ticket_id: string,
     *   tenant_id: string,
     *   status: string,
     *   error_message?: string|null,
     *   sent_at?: string|null,
     *   delivered_at?: string|null,
     *   read_at?: string|null
     * } $payload
     */
    public function broadcastMessageStatus(array $payload): void
    {
        $this->broadcastEvent('chat.message.status', $payload);
    }

    /**
     * Notificar clientes sobre nova mensagem recebida.
     *
     * @param  array{
     *   ticket_id: string,
     *   tenant_id: string,
     *   message: array<string, mixed>
     * } $payload
     */
    public function broadcastNewMessage(array $payload): void
    {
        $this->broadcastEvent('chat.message.new', $payload);
    }

    /**
     * @param  list<string>  $rooms
     * @param  array<string, mixed>  $data
     */
    private function publishToRedis(string $event, string $tenantId, array $rooms, array $data): bool
    {
        if ($tenantId === '') {
            Log::warning('[GatewayBroadcastService] Skip publish without tenant_id', [
                'event' => $event,
            ]);

            return false;
        }

        try {
            $payload = [
                'event' => $event,
                'tenant_id' => $tenantId,
                'rooms' => $rooms,
                'data' => $data,
                'trace_id' => $this->resolveTraceId(),
                'emitted_at' => now()->toIso8601String(),
                'version' => 'v1',
            ];

            Redis::connection('gateway')->publish(
                'ws.events',
                json_encode($payload, JSON_THROW_ON_ERROR)
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('[GatewayBroadcastService] Failed to publish ws.events', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendHttpEvent(string $event, array $data, string $room): void
    {
        try {
            $apiKey = config('services.gateway.api_key');

            Http::connectTimeout(1)->timeout(config('services.gateway.timeout', 3))
                ->withHeaders($apiKey ? ['X-API-Key' => $apiKey] : [])
                ->post("{$this->gatewayUrl}/internal/broadcast/event", [
                    'event' => $event,
                    'room' => $room,
                    'data' => $data,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[GatewayBroadcastService] Failed to broadcast event via HTTP fallback', [
                'event' => $event,
                'room' => $room,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveRooms(string $tenantId, ?string $room): array
    {
        if ($tenantId === '') {
            return $room !== null ? [$room] : [];
        }

        $tenantRoom = 'tenant:'.$tenantId;
        if ($room === null || $room === '') {
            return [$tenantRoom];
        }

        if ($room === $tenantRoom) {
            return [$tenantRoom];
        }

        return array_values(array_unique([$room, $tenantRoom]));
    }

    private function resolveTraceId(): string
    {
        try {
            $request = request();
            $headerTraceId = $request->header('X-Trace-Id') ?? $request->header('X-Request-Id');
            if (is_string($headerTraceId) && $headerTraceId !== '') {
                return $headerTraceId;
            }
        } catch (\Throwable) {
            // ignore and fallback to UUID
        }

        return (string) Str::uuid();
    }

    /**
     * Valida isolamento de tenant para evitar cross-tenant broadcasts.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws \RuntimeException Se tenant_id não corresponder ao usuário autenticado.
     */
    private function validateTenantIsolation(array $payload): void
    {
        $user = auth()->user();
        $authenticatedTenantId = $user !== null ? $user->tenant_id : null;
        $payloadTenantId = $payload['tenant_id'] ?? $payload['data']['tenant_id'] ?? null;

        // If no authenticated user, require payload to have tenant_id (no blind bypass)
        if (! $authenticatedTenantId) {
            if (! $payloadTenantId) {
                Log::error('[GatewayBroadcastService] Broadcast without auth and without tenant_id', [
                    'payload_keys' => array_keys($payload),
                ]);

                throw new \RuntimeException(
                    'Tenant isolation violation: No authenticated user and no tenant_id in payload'
                );
            }

            return;
        }

        // Se payload não tem tenant_id, permitir (console/job context)
        if (! $payloadTenantId) {
            return;
        }

        // Validar correspondência
        if ($authenticatedTenantId !== $payloadTenantId) {
            Log::error('[GatewayBroadcastService] Tenant isolation violation', [
                'authenticated' => $authenticatedTenantId,
                'payload' => $payloadTenantId,
            ]);

            throw new \RuntimeException(
                'Tenant isolation violation: Cannot broadcast to different tenant'
            );
        }
    }
}
