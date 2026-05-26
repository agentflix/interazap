<?php

declare(strict_types=1);

namespace Domain\Ai\Console\Commands;

use Domain\Ai\Services\ToolDispatcherService;
use Illuminate\Console\Command;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Redis;

/**
 * Comando para consumir e executar requisições de ferramentas via Redis Stream.
 *
 * Lê o stream ai.tool.request, executa a tool via ToolDispatcherService e
 * publica o resultado na chave Redis RPC (reply_key) aguardada pelo gateway.
 * Mensagens com falha são encaminhadas para o stream DLQ ai.tool.dlq.
 */
final class ConsumeToolRequestsCommand extends Command
{
    protected $signature = 'ai:consume-tool-requests {--once : Process a single read cycle} {--max-runtime=0 : Maximum runtime in seconds (0 = unlimited)}';

    protected $description = 'Consume ai.tool.request stream messages, execute tools, and reply via Redis list RPC.';

    private string $stream = 'ai.tool.request';

    private string $group = 'api-ai-tool-worker';

    private string $dlqStream = 'ai.tool.dlq';

    private int $batchSize = 20;

    private int $blockMs = 1000;

    public function __construct(private readonly ToolDispatcherService $toolDispatcher)
    {
        parent::__construct();
    }

    /**
     * Executa o consumo contínuo do stream Redis de requisições de tools.
     */
    public function handle(): int
    {
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
                        $this->processMessage($normalized);
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
     * @param  array<string, mixed>  $message
     */
    private function processMessage(array $message): void
    {
        $replyKey = (string) ($message['reply_key'] ?? '');

        try {
            $toolName = (string) ($message['tool_name'] ?? '');
            $parameters = is_array($message['parameters'] ?? null) ? $message['parameters'] : [];
            $context = is_array($message['context'] ?? null) ? $message['context'] : [];

            if ($toolName === '' || $replyKey === '') {
                throw new \RuntimeException('Invalid ai.tool.request payload.');
            }

            $result = $this->toolDispatcher->dispatch($toolName, $parameters, $context)->toArray();

            $responsePayload = json_encode([
                'request_id' => (string) ($message['request_id'] ?? ''),
                ...$result,
            ], JSON_THROW_ON_ERROR);

            $redis = Redis::connection(config('gateway.redis.connection', 'gateway'));
            $redis->lpush($replyKey, $responsePayload);
            $redis->expire($replyKey, 10);
        } catch (\Throwable $exception) {
            $this->publishDlq($message, $exception);

            if ($replyKey !== '') {
                try {
                    $redis = Redis::connection(config('gateway.redis.connection', 'gateway'));
                    $redis->lpush($replyKey, json_encode([
                        'request_id' => (string) ($message['request_id'] ?? ''),
                        'success' => false,
                        'message' => $exception->getMessage(),
                        'data' => [],
                    ], JSON_THROW_ON_ERROR));
                    $redis->expire($replyKey, 10);
                } catch (\Throwable) {
                }
            }
        }
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

        if (! isset($normalized['tool_name']) || ! isset($normalized['reply_key'])) {
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publishDlq(array $payload, \Throwable $exception): void
    {
        $dlqPayload = [
            'event' => 'ai.tool.request.failed',
            'error' => $exception->getMessage(),
            'failed_at' => now()->toIso8601String(),
            'payload' => $payload,
        ];

        $fields = $this->normalizeStreamFields($dlqPayload);
        $connection = Redis::connection(config('gateway.redis.connection', 'gateway'));

        if ($connection instanceof PredisConnection) {
            $arguments = ['XADD', $this->dlqStream, '*'];

            foreach ($fields as $key => $value) {
                $arguments[] = (string) $key;
                $arguments[] = $value;
            }

            $connection->client()->executeRaw($arguments);

            return;
        }

        $connection->xadd($this->dlqStream, '*', $fields);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    private function normalizeStreamFields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $key => $value) {
            if (is_string($value)) {
                $normalized[$key] = $value;

                continue;
            }

            if (is_int($value) || is_float($value)) {
                $normalized[$key] = (string) $value;

                continue;
            }

            if (is_bool($value)) {
                $normalized[$key] = $value ? '1' : '0';

                continue;
            }

            if ($value === null) {
                $normalized[$key] = '';

                continue;
            }

            try {
                $normalized[$key] = json_encode($value, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $normalized[$key] = (string) json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
            }
        }

        return $normalized;
    }
}
