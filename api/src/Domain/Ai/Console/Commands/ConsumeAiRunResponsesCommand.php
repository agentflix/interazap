<?php

declare(strict_types=1);

namespace Domain\Ai\Console\Commands;

use Domain\Ai\Jobs\AiRunTrackerJob;
use Illuminate\Console\Command;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Redis;

/**
 * Comando para consumir mensagens do stream Redis de respostas de runs de IA.
 *
 * Lê o stream ai.run.response via Consumer Group e despacha AiRunTrackerJob
 * para cada mensagem recebida. Projetado para rodar continuamente (55s por ciclo)
 * com suporte a modo único (--once) para testes.
 */
final class ConsumeAiRunResponsesCommand extends Command
{
    protected $signature = 'ai:consume-run-responses {--once : Process a single read cycle} {--max-runtime=0 : Maximum runtime in seconds (0 = unlimited)}';

    protected $description = 'Consume ai.run.response stream messages and dispatch AiRunTrackerJob.';

    private string $stream = 'ai.run.response';

    private string $group = 'api-ai-run-tracker';

    private int $batchSize = 50;

    private int $blockMs = 1000;

    /**
     * Executa o consumo contínuo do stream Redis de respostas de runs.
     */
    public function handle(): int
    {
        $this->stream = (string) config('gateway.streams.ai_response', 'ai.run.response');

        $consumer = sprintf('%s-%s', gethostname() ?: 'api', (string) getmypid());
        $this->ensureGroupExists();

        $maxRuntime = (int) $this->option('max-runtime');
        $deadline = $maxRuntime > 0 ? microtime(true) + $maxRuntime : null;

        do {
            $messages = $this->readMessages($consumer);
            if ($messages === null || $messages === []) {
                if ($this->option('once')) {
                    break;
                }

                if ($deadline !== null && microtime(true) >= $deadline) {
                    break;
                }

                continue;
            }

            foreach ($messages as $streamName => $entries) {
                foreach ($entries as $entryId => $payload) {
                    $normalized = $this->normalizePayload($payload);
                    if ($normalized !== null) {
                        AiRunTrackerJob::dispatch($normalized);
                    }

                    $this->ackEntry($streamName, (string) $entryId);
                }
            }

            if ($this->option('once')) {
                break;
            }

            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }
        } while (true);

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>|null
     */
    private function readMessages(string $consumer): ?array
    {
        $connection = Redis::connection(config('gateway.redis.connection', 'gateway'));

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
                if (str_contains(strtoupper($raw), 'NOGROUP')) {
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

    private function ensureGroupExists(): void
    {
        $connection = Redis::connection(config('gateway.redis.connection', 'gateway'));

        try {
            if ($connection instanceof PredisConnection) {
                $connection->client()->executeRaw([
                    'XGROUP', 'CREATE', $this->stream, $this->group, '$', 'MKSTREAM',
                ]);

                return;
            }

            $connection->xgroup('CREATE', $this->stream, $this->group, '$', true);
        } catch (\Throwable $exception) {
            if (str_contains($exception->getMessage(), 'BUSYGROUP')) {
                return;
            }

            throw $exception;
        }
    }

    private function ackEntry(string $stream, string $id): void
    {
        $connection = Redis::connection(config('gateway.redis.connection', 'gateway'));

        if ($connection instanceof PredisConnection) {
            $connection->client()->executeRaw([
                'XACK', $stream, $this->group, $id,
            ]);

            return;
        }

        $connection->xack($stream, $this->group, [$id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function normalizePayload(array $payload): ?array
    {
        if (isset($payload['payload']) && is_string($payload['payload'])) {
            $decoded = json_decode($payload['payload'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $normalized = [];
        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $normalized[$key] = is_array($decoded) ? $decoded : $value;

                continue;
            }

            $normalized[$key] = $value;
        }

        if (! isset($normalized['run_id']) || ! isset($normalized['tenant_id'])) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>|null  $raw
     * @return array<string, array<string, array<string, mixed>>>|null
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
                for ($index = 0; $index < $total; $index += 2) {
                    $field = $entryFields[$index] ?? null;
                    $value = $entryFields[$index + 1] ?? null;

                    if ($field !== null) {
                        $paired[(string) $field] = $value;
                    }
                }

                $messages[$streamName][$entryId] = $paired;
            }
        }

        return $messages;
    }
}
