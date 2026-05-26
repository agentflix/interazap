<?php

declare(strict_types=1);

namespace Domain\Billing\Console\Commands;

use Domain\Billing\Actions\BillingAsaasWebhookAction;
use Domain\Billing\DTOs\BillingWebhookDTO;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Events\RealtimeBroadcastEvent;
use Illuminate\Console\Command;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Consome o stream Redis billing.payment_received e aplica regras de billing.
 */
final class BillingWebhookConsumer extends Command
{
    protected $signature = 'streams:billing-consume {--once : Processa uma vez e sai}';

    protected $description = 'Consume billing Redis stream and process payment events';

    private string $stream;

    private string $group;

    private string $consumerPrefix;

    private string $instanceWebhookToken;

    private string $realtimeEvent;

    private int $batchSize;

    private int $blockMs;

    public function __construct()
    {
        parent::__construct();

        $config = (array) config('billing.streams.payment_received');

        $this->stream = (string) ($config['name'] ?? 'billing.payment_received');
        $this->group = (string) ($config['group'] ?? 'laravel-billing');
        $this->consumerPrefix = (string) ($config['consumer_prefix'] ?? 'billing-worker-');
        $this->instanceWebhookToken = (string) ($config['instance_webhook_token'] ?? 'billing-stream');
        $this->realtimeEvent = (string) ($config['realtime_event'] ?? 'billing.payment_received');
        $this->batchSize = (int) ($config['batch_size'] ?? 10);
        $this->blockMs = (int) ($config['block_ms'] ?? 2000);
    }

    /**
     * Executa o loop de consumo do stream Redis de billing.
     */
    public function handle(): int
    {
        $consumer = $this->consumerPrefix.Str::random(6);
        $this->ensureGroupExists();

        do {
            $messages = $this->readMessages($consumer);

            if (empty($messages)) {
                continue;
            }

            foreach ($messages as $stream => $entries) {
                foreach ($entries as $id => $fields) {
                    try {
                        $this->processEntry($id, $fields);
                    } catch (\Throwable $e) {
                        Log::error('BillingWebhookConsumer: Failed to process entry', [
                            'stream_id' => $id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    $this->ackEntry($stream, $id);
                }
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    /**
     * Processa uma entrada do stream: decodifica payload, resolve tenant e executa a action de webhook.
     *
     * @param  array<string, string>  $fields  Campos da entrada do Redis Stream
     */
    private function processEntry(string $id, array $fields): void
    {
        $payload = $this->decodePayload($fields);
        $tenantId = $payload['tenant_id'] ?? Config::get('app.default_tenant_id');

        if (! $tenantId) {
            Log::warning('Billing stream: tenant_id ausente', ['stream_id' => $id]);

            return;
        }

        $tenant = PlatformTenant::query()->find($tenantId);
        if (! $tenant) {
            Log::warning('Billing stream: tenant não encontrado', [
                'stream_id' => $id,
                'tenant_id' => $tenantId,
            ]);

            return;
        }

        $dto = BillingWebhookDTO::fromArray($payload);

        /** @var BillingAsaasWebhookAction $action */
        $action = app(BillingAsaasWebhookAction::class);

        // A action usa insertOrIgnore (atômico na DB) via storeIfNotExists para
        // garantir idempotência sem janela de corrida entre HTTP e consumer.
        // skipIdempotency=false é obrigatório em produção.
        $result = $action->handle($tenant, $dto);

        if (! $result['created']) {
            Log::info('Billing stream: evento duplicado ignorado pela idempotência da action', [
                'stream_id' => $id,
                'tenant_id' => $tenantId,
                'idempotency_key' => $dto->idempotencyKey,
            ]);
        }

        $this->logMetrics($id, $dto, $payload);

        // Fan-out realtime (Reverb)
        if ($result['created'] || $result['invoice_updated']) {
            event(new RealtimeBroadcastEvent([
                'tenant.'.$tenantId,
            ], $this->realtimeEvent, $payload));
        }
    }

    /**
     * Decodifica os campos da entrada do Redis Stream em um array associativo.
     *
     * Campos que parecem JSON são decodificados automaticamente.
     *
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    private function decodePayload(array $fields): array
    {
        $payload = [];
        foreach ($fields as $key => $value) {
            $payload[$key] = $this->tryDecode($value);
        }

        return $payload;
    }

    /** Tenta decodificar um valor JSON; retorna a string original se não for JSON válido. */
    private function tryDecode(string $value): mixed
    {
        $trim = trim($value);
        if (
            (str_starts_with($trim, '{') && str_ends_with($trim, '}'))
            || (str_starts_with($trim, '[') && str_ends_with($trim, ']'))
        ) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                // Log decode failures for debugging (OBS-CRIT-002)
                \Illuminate\Support\Facades\Log::debug('BillingWebhookConsumer: JSON decode failed', [
                    'error' => $e->getMessage(),
                    'value_preview' => substr($value, 0, 100),
                ]);

                return $value;
            }
        }

        return $value;
    }

    /**
     * Lê mensagens pendentes do grupo no Redis Stream, compatível com Predis e PhpRedis.
     *
     * @return array<string, array<string, array<string, string>>>|null
     */
    private function readMessages(string $consumer): ?array
    {
        $connection = Redis::connection('gateway');

        if ($connection instanceof PredisConnection) {
            $raw = $connection->client()->executeRaw([
                'XREADGROUP',
                'GROUP', $this->group, $consumer,
                'COUNT', (string) $this->batchSize,
                'BLOCK', (string) $this->blockMs,
                'STREAMS',
                $this->stream,
                '>',
            ]);

            if (is_string($raw)) {
                if (stripos($raw, 'NOGROUP') !== false) {
                    $this->ensureGroupExists();

                    return null;
                }

                throw new \RuntimeException($raw);
            }

            return $this->normalizePredisMessages($raw);
        }

        return $connection->xreadgroup(
            $this->group,
            $consumer,
            [$this->stream => '>'],
            $this->batchSize,
            $this->blockMs,
        );
    }

    /** Confirma (ACK) o processamento de uma entrada do stream pelo seu ID (compatível com Predis). */
    private function ackEntry(string $stream, string $id): void
    {
        $connection = Redis::connection('gateway');
        if ($connection instanceof PredisConnection) {
            $connection->client()->executeRaw([
                'XACK', $stream, $this->group, $id,
            ]);
        } else {
            $connection->xack($stream, $this->group, [$id]);
        }
    }

    /** Cria o grupo de consumidores no stream se ainda não existir (MKSTREAM). */
    private function ensureGroupExists(): void
    {
        try {
            $connection = Redis::connection('gateway');
            if ($connection instanceof PredisConnection) {
                $connection->client()->executeRaw([
                    'XGROUP', 'CREATE', $this->stream, $this->group, '$', 'MKSTREAM',
                ]);
            } else {
                $connection->xgroup('CREATE', $this->stream, $this->group, '$', true);
            }
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'BUSYGROUP')) {
                return;
            }

            Log::error('BillingWebhookConsumer: Failed to create Redis group', [
                'group' => $this->group,
                'stream' => $this->stream,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normaliza a resposta crua do Predis para o formato associativo padrão do PhpRedis.
     *
     * @param  array<int, mixed>|null  $raw
     * @return array<string, array<string, array<string, string>>>|null
     */
    private function normalizePredisMessages(?array $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $messages = [];

        foreach ($raw as $streamChunk) {
            if (! is_array($streamChunk) || count($streamChunk) < 2) {
                continue;
            }
            [$streamName, $entries] = $streamChunk;
            if (! is_string($streamName) || ! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! is_array($entry) || count($entry) < 2) {
                    continue;
                }
                [$entryId, $entryFields] = $entry;
                if (! is_string($entryId) || ! is_array($entryFields)) {
                    continue;
                }

                $paired = [];
                $total = count($entryFields);
                for ($i = 0; $i < $total; $i += 2) {
                    $k = $entryFields[$i] ?? null;
                    $v = $entryFields[$i + 1] ?? null;
                    if ($k !== null) {
                        $paired[$k] = $v;
                    }
                }
                $messages[$streamName][$entryId] = $paired;
            }
        }

        return $messages === [] ? null : $messages;
    }

    /** Verifica se a coluna stream_id existe na tabela shared_webhook_events. */
    private function hasStreamIdColumn(): bool
    {
        return Schema::hasColumn('shared_webhook_events', 'stream_id');
    }

    /**
     * Registra métricas de processamento do evento, incluindo lag calculado a partir de received_at.
     *
     * @param  array<string, mixed>  $payload
     */
    private function logMetrics(string $streamId, BillingWebhookDTO $dto, array $payload): void
    {
        $receivedAt = $payload['received_at'] ?? null;
        $correlationId = $payload['correlation_id'] ?? null;
        $lagMs = null;

        if (is_string($receivedAt)) {
            $receivedTimestamp = strtotime($receivedAt);
            if ($receivedTimestamp !== false) {
                $lagMs = (int) (microtime(true) * 1000) - ($receivedTimestamp * 1000);
            }
        }

        Log::info('Billing stream event processed', [
            'stream_id' => $streamId,
            'tenant_id' => $payload['tenant_id'] ?? null,
            'event_type' => $dto->eventType,
            'provider_event_id' => $dto->providerEventId,
            'idempotency_key' => $dto->idempotencyKey,
            'correlation_id' => $correlationId,
            'lag_ms' => $lagMs,
        ]);
    }
}
