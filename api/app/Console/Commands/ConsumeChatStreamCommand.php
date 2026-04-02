<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Chat\Actions\ChatWebhookIngestor;
use Illuminate\Console\Command;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Consome o stream Redis chat.inbound_message_received e processa ingestão de negócio.
 */
class ConsumeChatStreamCommand extends Command
{
    protected $signature = 'streams:chat-consume {--once : Processa uma vez e sai}';

    protected $description = 'Consume chat inbound Redis stream and process business ingestion';

    private string $stream = 'chat.inbound_message_received';

    private string $group = 'laravel-chat-inbound';

    private int $fetchCount = 10;

    private int $blockMilliseconds = 50;

    /**
     * @var array<int, int>
     */
    private array $readLatencySamples = [];

    private bool $streamMetricsEnabled = true;

    private int $streamMetricsWindowSize = 200;

    public function handle(): int
    {
        $this->blockMilliseconds = max(10, (int) config('chat.stream_consumer.block_ms', 50));
        $this->streamMetricsEnabled = (bool) config('chat.stream_consumer.metrics_enabled', true);
        $this->streamMetricsWindowSize = max(20, (int) config('chat.stream_consumer.metrics_window_size', 200));

        $consumer = 'chat-worker-'.Str::random(6);
        $this->ensureGroupExists();

        do {
            $messages = $this->readMessages($consumer);

            if ($messages === null) {
                continue;
            }

            foreach ($messages as $stream => $entries) {
                foreach ($entries as $id => $fields) {
                    try {
                        $this->processEntry($fields);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('ConsumeChatStream: Failed to process entry', [
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
     * @return array<string, array<string, array<string, string>>>|null
     */
    private function readMessages(string $consumer): ?array
    {
        $readStartedAt = microtime(true);
        $connection = Redis::connection('gateway');

        if ($connection instanceof PredisConnection) {
            $raw = $connection->client()->executeRaw([
                'XREADGROUP',
                'GROUP', $this->group, $consumer,
                'COUNT', (string) $this->fetchCount,
                'BLOCK', (string) $this->blockMilliseconds,
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

            $messages = $this->normalizePredisMessages($raw);
            $this->recordReadLatency($readStartedAt, $messages);

            return $messages;
        }

        $messages = $connection->xreadgroup(
            $this->group,
            $consumer,
            [$this->stream => '>'],
            $this->fetchCount,
            $this->blockMilliseconds,
        );

        $this->recordReadLatency($readStartedAt, $messages);

        return $messages;
    }

    /**
     * Registra latência do XREADGROUP e loga p50/p95/p99 em janela deslizante.
     *
     * @param  float  $readStartedAt  Timestamp inicial em segundos (microtime).
     * @param  array<string, array<string, array<string, string>>>|null  $messages  Mensagens retornadas.
     */
    private function recordReadLatency(float $readStartedAt, ?array $messages): void
    {
        if (! $this->streamMetricsEnabled) {
            return;
        }

        $durationMs = (int) round((microtime(true) - $readStartedAt) * 1000);
        $this->readLatencySamples[] = $durationMs;

        if (count($this->readLatencySamples) > $this->streamMetricsWindowSize) {
            array_shift($this->readLatencySamples);
        }

        if (count($this->readLatencySamples) < $this->streamMetricsWindowSize) {
            return;
        }

        $sortedSamples = $this->readLatencySamples;
        sort($sortedSamples);

        $messageCount = 0;
        if (is_array($messages)) {
            foreach ($messages as $entries) {
                $messageCount += count($entries);
            }
        }

        \Illuminate\Support\Facades\Log::info('ConsumeChatStream: XREADGROUP rolling latency', [
            'block_ms' => $this->blockMilliseconds,
            'sample_size' => count($sortedSamples),
            'p50_ms' => $this->percentile($sortedSamples, 50),
            'p95_ms' => $this->percentile($sortedSamples, 95),
            'p99_ms' => $this->percentile($sortedSamples, 99),
            'last_read_ms' => $durationMs,
            'message_count' => $messageCount,
        ]);
    }

    /**
     * Calcula percentil aproximado para um vetor já ordenado.
     *
     * @param  array<int, int>  $sortedSamples  Amostras ordenadas ascendente.
     * @param  int  $percentile  Percentil desejado (0-100).
     */
    private function percentile(array $sortedSamples, int $percentile): int
    {
        $total = count($sortedSamples);
        if ($total === 0) {
            return 0;
        }

        $index = (int) ceil(($percentile / 100) * $total) - 1;
        $safeIndex = max(0, min($total - 1, $index));

        return $sortedSamples[$safeIndex];
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function processEntry(array $fields): void
    {
        $payload = $this->decodePayload($fields);
        $tenantId = $payload['tenant_id'] ?? Config::get('app.default_tenant_id');

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);
    }

    /**
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
                \Illuminate\Support\Facades\Log::debug('ConsumeChatStream: JSON decode failed', [
                    'error' => $e->getMessage(),
                    'value_preview' => substr($value, 0, 100),
                ]);

                return $value;
            }
        }

        return $value;
    }

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

            \Illuminate\Support\Facades\Log::error('ConsumeChatStream: Failed to create Redis group', [
                'group' => $this->group,
                'stream' => $this->stream,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Acknowledges a stream entry by ID (Predis-compatible).
     */
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

    /**
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
            if (! is_array($streamChunk)) {
                continue;
            }
            if (count($streamChunk) < 2) {
                continue;
            }
            [$streamName, $entries] = $streamChunk;
            if (! is_string($streamName)) {
                continue;
            }
            if (! is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                if (count($entry) < 2) {
                    continue;
                }
                [$entryId, $entryFields] = $entry;
                if (! is_string($entryId)) {
                    continue;
                }
                if (! is_array($entryFields)) {
                    continue;
                }

                $messages[$streamName][$entryId] = $this->pairRedisFields($entryFields);
            }
        }

        return $messages === [] ? null : $messages;
    }

    /**
     * @param  array<int|string, string>  $fields
     * @return array<string, string>
     */
    private function pairRedisFields(array $fields): array
    {
        $paired = [];
        $total = count($fields);

        for ($index = 0; $index < $total; $index += 2) {
            $key = $fields[$index] ?? null;
            $value = $fields[$index + 1] ?? null;

            if ($key === null) {
                continue;
            }

            $paired[$key] = $value;
        }

        return $paired;
    }
}
