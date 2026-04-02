<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatWebhookEventDTO;
use Domain\Chat\Jobs\ChatWebhookIngressJob;
use Domain\Chat\Models\ChatWebhookEvent;
use Domain\Chat\Repositories\ChatInstanceRepository;
use Domain\Chat\Services\ChatBroadcastService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Casos de Uso para Processamento de Webhooks Uazapi.
 *
 * Responsável por receber payloads já normalizados pelo Gateway,
 * evitar duplicidade (idempotência), persistir e disparar o fluxo
 * de ingestão e eventos em tempo real.
 *
 * @category Actions
 */
final class ChatUazapiWebhookActions
{
    public function __construct(
        private readonly ChatInstanceRepository $instances,
    ) {}

    /**
     * Ponto de entrada para processamento de um webhook Uazapi.
     *
     * Resolve a instância, aplica regras de idempotência,
     * persiste o evento bruto, inicia a ingestão e notifica via WebSocket.
     *
     * Nota: O payload já vem normalizado pelo Gateway.
     *
     * @param  string  $token  Token de autenticação do webhook/instância.
     * @param  array<string, mixed>  $payload  Dados normalizados recebidos do Gateway.
     * @return array<string, mixed> Resultado da operação (success, duplicate, event_id, etc).
     *
     * @throws RuntimeException Se o token for inválido.
     */
    public function handle(string $token, array $payload): array
    {
        $instance = $this->instances->resolveInstanceByToken($token);
        if (! $instance) {
            $inactive = $this->instances->findByWebhookToken($token);
            if ($inactive) {
                logger()->warning('[ChatUazapiWebhook] Webhook ignorado: instância inativa', [
                    'token' => $this->maskToken($token),
                    'instance_id' => (string) $inactive->id,
                    'tenant_id' => (string) $inactive->tenant_id,
                ]);

                return ['success' => true, 'ignored' => true, 'reason' => 'inactive'];
            }

            throw new RuntimeException('Token de webhook inválido');
        }
        $tenantId = (string) $instance->tenant_id;

        // Payload já normalizado pelo Gateway; enriquecemos com dados da instância
        $normalized = $payload;
        $normalizedEventType = $this->normalizeEventType($payload);
        if ($normalizedEventType !== null) {
            $normalized['event_type'] = $normalizedEventType;
        }
        $normalized['provider'] = $normalized['provider'] ?? 'uazapi';
        $normalized['instance_webhook_token'] = $token;
        $normalized['tenant_id'] = $tenantId;
        $normalized['instance_id'] = (string) $instance->id;

        $dto = ChatWebhookEventDTO::fromNormalized($normalized);
        $payloadArray = $dto->toArray();

        $eventType = strtolower((string) ($payloadArray['event_type'] ?? ''));
        $isConnectionEvent = str_starts_with($eventType, 'connection');

        $descriptor = $this->buildIdempotencyDescriptor($payloadArray);
        $this->prepareConnectionDedup($descriptor);

        // Para eventos de conexão, sempre processar (não verificar idempotência de cache)
        // Isso permite que atualizações de status e emissões realtime ocorram mesmo em reconexões
        if (! $isConnectionEvent) {
            if (! $this->acquireIdempotency($descriptor['key'])) {
                logger()->debug('[ChatUazapiWebhook] Duplicate skipped', ['idempotency_key' => $descriptor['key']]);

                return ['success' => true, 'duplicate' => true];
            }
        }

        $streamId = (string) (data_get($payloadArray, 'message.id') ?: $descriptor['key']);
        if (strlen($streamId) > 255) {
            $streamId = hash('sha256', $streamId);
        }

        $eventId = (string) Str::orderedUuid();
        $payloadJson = json_encode($payloadArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $inserted = ChatWebhookEvent::query()->insertOrIgnore([
            'id' => $eventId,
            'tenant_id' => $tenantId,
            'domain' => 'chat',
            'stream_id' => $streamId,
            'idempotency_key' => $descriptor['key'],
            'provider' => 'uazapi',
            'instance_webhook_token' => $token,
            'event_type' => $payloadArray['event_type'] ?? null,
            'direction' => $payloadArray['direction'] ?? null,
            'payload' => $payloadJson,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0 && ! $isConnectionEvent) {
            logger()->debug('[ChatUazapiWebhook] Duplicate skipped (db)', [
                'idempotency_key' => $descriptor['key'],
                'stream_id' => $streamId,
            ]);

            return ['success' => true, 'duplicate' => true];
        }

        ChatWebhookIngressJob::dispatch($tenantId, $payloadArray, $descriptor);

        return [
            'success' => true,
            'event_id' => $eventId,
            'idempotency_key' => $descriptor['key'],
        ];
    }

    /**
     * Constrói um descritor de idempotência com base no tipo de evento.
     *
     * Garante que mensagens ou eventos de status repetidos não causem re-processamento.
     *
     * @param  array<string, mixed>  $payload  Dados normalizados.
     * @return array{key:string,provider:string,eventType:string,normalizedEventType:string,token:string,discriminator:string|null}
     */
    private function buildIdempotencyDescriptor(array $payload): array
    {
        $eventType = strtolower((string) ($payload['event_type'] ?? 'unknown'));
        $provider = (string) ($payload['provider'] ?? 'uazapi');
        $token = (string) ($payload['instance_webhook_token'] ?? '');

        $messageId = Arr::get($payload, 'message.id');
        $timestamp = Arr::get($payload, 'message.timestamp')
            ?? Arr::get($payload, 'raw.message.timestamp')
            ?? Arr::get($payload, 'raw.message.messageTimestamp');

        $discriminator = $messageId ? (string) $messageId : null;
        if ($discriminator && $timestamp) {
            $discriminator .= ':'.$timestamp;
        }
        if (! $discriminator && $timestamp) {
            $discriminator = 'ts:'.$timestamp;
        }

        if (str_starts_with($eventType, 'connection')) {
            $discriminator = $this->resolveConnectionStatus($payload) ?? $discriminator;
        } elseif ($eventType === 'messages_update') {
            $discriminator = $this->resolveMessageUpdateDiscriminator($payload);
        }

        if (! $discriminator) {
            $discriminator = 'generic';
        }

        $rawKey = sprintf('idempo:%s:%s:%s:%s', $provider, $eventType, $token, $discriminator);
        $key = strlen($rawKey) > 255 ? 'idempo_hash:'.hash('sha256', $rawKey) : $rawKey;

        return [
            'key' => $key,
            'provider' => $provider,
            'eventType' => $eventType,
            'normalizedEventType' => str_starts_with($eventType, 'connection') ? 'connection' : $eventType,
            'token' => $token,
            'discriminator' => $discriminator,
        ];
    }

    /**
     * Normaliza o tipo de evento recebido em diferentes formatos.
     *
     * @param  array<string, mixed>  $payload  Dados recebidos pelo webhook.
     * @return string|null Tipo normalizado do evento.
     */
    private function normalizeEventType(array $payload): ?string
    {
        $candidate = $payload['event_type']
            ?? $payload['EventType']
            ?? $payload['eventType']
            ?? $payload['event']
            ?? null;

        if (! is_string($candidate)) {
            return null;
        }

        $eventType = strtolower(trim($candidate));
        if ($eventType === '') {
            return null;
        }

        return match ($eventType) {
            'connected', 'disconnected', 'online', 'offline', 'open', 'close', 'qr', 'connecting' => 'connection',
            default => $eventType,
        };
    }

    /**
     * Tenta adquirir uma trava de idempotência no cache.
     *
     * @param  string  $key  Chave de idempotência única.
     * @return bool True se a trava foi adquirida (primeiro processamento), false caso contrário.
     */
    private function acquireIdempotency(string $key): bool
    {
        return $this->cache()->add($key, true, 600);
    }

    /**
     * Limpa chave antiga de conexão quando status muda para permitir novo processamento.
     *
     * Permite que eventos de status de conexão diferentes (ex: de 'connecting' para 'connected')
     * sejam processados sequencialmente ignorando o bloqueio de idempotência do status anterior.
     *
     * @param  array{provider:string,eventType:string,token:string,discriminator:string|null,key:string,normalizedEventType:string}  $descriptor
     */
    private function prepareConnectionDedup(array $descriptor): void
    {
        if ($descriptor['normalizedEventType'] !== 'connection' || ! $descriptor['token'] || ! $descriptor['discriminator']) {
            return;
        }

        $trackerKey = sprintf('connection:last_status:%s:%s', $descriptor['provider'], $descriptor['token']);
        $last = $this->cache()->get($trackerKey);

        if (is_string($last) && $last !== $descriptor['discriminator']) {
            $previousKey = $this->composeIdempotencyKey($descriptor['provider'], $descriptor['eventType'], $descriptor['token'], $last);
            $this->cache()->forget($previousKey);
        }

        $this->cache()->put($trackerKey, $descriptor['discriminator'], 86400);
    }

    /**
     * Extrai o status da conexão a partir do payload original uazapi.
     *
     * @param  array<string, mixed>  $payload  Dados normalizados.
     * @return string|null Status extraído (ex: 'connected', 'disconnected').
     */
    private function resolveConnectionStatus(array $payload): ?string
    {
        $connection = Arr::get($payload, 'raw.instance') ?? Arr::get($payload, 'raw.status');
        $status = Arr::get($payload, 'raw.status.status') ?? Arr::get($payload, 'raw.status');

        $candidate = $status ?? $connection['status'] ?? null;
        if (is_string($candidate)) {
            return $candidate;
        }

        if (is_array($status)) {
            $connected = $status['connected'] ?? $status['loggedIn'] ?? null;
            if (is_bool($connected)) {
                return $connected ? 'connected' : 'disconnected';
            }
        }

        return null;
    }

    /**
     * Define o discriminador para atualizações de mensagens (status de leitura/entrega).
     *
     * Combina IDs de mensagens e o novo estado para formar uma chave única.
     *
     * @param  array<string, mixed>  $payload  Dados normalizados.
     * @return string Discriminador para a chave de idempotência.
     */
    private function resolveMessageUpdateDiscriminator(array $payload): string
    {
        $outerRaw = Arr::get($payload, 'raw', []);
        $event = Arr::get($outerRaw, 'raw.event') ?? Arr::get($outerRaw, 'event');
        $event = is_array($event) ? $event : [];

        $messageIds = Arr::get($event, 'MessageIDs', []);
        $messageIds = is_array($messageIds) ? array_filter($messageIds, fn ($id) => is_string($id) && $id !== '') : [];
        $statusType = (string) ($event['Type'] ?? $outerRaw['state'] ?? 'unknown');

        if ($messageIds !== []) {
            return implode(',', $messageIds).'_'.$statusType;
        }

        $timestamp = $event['Timestamp'] ?? null;
        if (is_string($timestamp) || is_numeric($timestamp)) {
            return 'ts_'.$timestamp.'_'.$statusType;
        }

        return 'generic';
    }

    /**
     * Composição bruta da chave de idempotência.
     *
     * @param  string  $provider  Nome do provedor.
     * @param  string  $eventType  Tipo do evento.
     * @param  string  $token  Token da instância.
     * @param  string  $discriminator  Valor único do payload.
     * @return string Chave formatada e opcionalmente resumida via hash.
     */
    private function composeIdempotencyKey(string $provider, string $eventType, string $token, string $discriminator): string
    {
        $raw = sprintf('idempo:%s:%s:%s:%s', $provider, $eventType, $token, $discriminator);

        return strlen($raw) > 255 ? 'idempo_hash:'.hash('sha256', $raw) : $raw;
    }

    /**
     * Emissão de eventos em tempo real sem depender do container.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $descriptor
     */
    public static function emitRealtimeStatic(ChatBroadcastService $broadcast, array $payload, array $descriptor): void
    {
        $eventType = strtolower((string) ($payload['event_type'] ?? ''));

        // Detectar eventos de conexão (connection, connection.update, etc.)
        if (str_starts_with($eventType, 'connection')) {
            $broadcast->emit('chat.channel.connection', [
                'tenant_id' => $payload['tenant_id'] ?? null,
                'instance_id' => $payload['instance_id'] ?? null,
                'token' => $payload['instance_webhook_token'] ?? null,
                'status' => self::resolveConnectionStatusStatic($payload) ?? 'connecting',
                'raw' => $payload,
            ]);
        }

        if ($eventType === 'messages_update') {
            $messageIds = Arr::wrap(data_get($payload, 'raw.raw.event.MessageIDs'));
            $statusType = strtolower((string) (data_get($payload, 'raw.raw.event.Type') ?? data_get($payload, 'raw.state') ?? 'unknown'));
            $normalizedStatus = self::normalizeMessageStatusStatic($statusType);
            $now = now()->toIso8601String();

            if ($normalizedStatus && $messageIds !== []) {
                foreach ($messageIds as $messageId) {
                    $broadcast->emit('chat.message.status', [
                        'message_id' => $messageId,
                        'external_id' => $messageId,
                        'status' => $normalizedStatus,
                        'delivered_at' => $normalizedStatus === 'delivered' ? $now : null,
                        'read_at' => $normalizedStatus === 'read' ? $now : null,
                        'tenant_id' => $payload['tenant_id'] ?? null,
                    ], $payload['tenant_id'] ? 'tenant:'.$payload['tenant_id'] : null);
                }
            }
        }
    }

    /**
     * Resolve status de conexão sem instância.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function resolveConnectionStatusStatic(array $payload): ?string
    {
        $connection = Arr::get($payload, 'raw.instance') ?? Arr::get($payload, 'raw.status');
        $status = Arr::get($payload, 'raw.status.status') ?? Arr::get($payload, 'raw.status');

        $candidate = $status ?? ($connection['status'] ?? null);
        if (is_string($candidate)) {
            return $candidate;
        }

        if (is_array($status)) {
            $connected = $status['connected'] ?? $status['loggedIn'] ?? null;
            if (is_bool($connected)) {
                return $connected ? 'connected' : 'disconnected';
            }
        }

        return null;
    }

    /**
     * Normaliza tipos de status do Uazapi sem instância.
     *
     * @param  string  $statusType  Tipo de status bruto.
     * @return string|null Status normalizado ('sent', 'delivered', 'read') ou null.
     */
    private static function normalizeMessageStatusStatic(string $statusType): ?string
    {
        $lower = strtolower($statusType);

        return match (true) {
            $lower === 'delivered' || $lower === 'delivery_ack' => 'delivered',
            $lower === 'read' || $lower === 'played' || $lower === 'viewed' => 'read',
            $lower === 'sent' || $lower === 'server_ack' => 'sent',
            default => null,
        };
    }

    /**
     * Masks the token for logs (e.g. ****-last8).
     *
     * @param  string  $token  Original token.
     * @return string Masked token.
     */
    private function maskToken(string $token): string
    {
        $suffix = substr($token, -8);

        return $suffix ? '****-'.$suffix : '****';
    }

    /**
     * Recupera o driver de cache para controle de idempotência.
     */
    private function cache(): \Illuminate\Contracts\Cache\Repository
    {
        $store = config('cache.default', 'array');

        return Cache::store($store);
    }
}
