<?php

declare(strict_types=1);

namespace Domain\Gateway\Services;

use Domain\Gateway\Contracts\GatewayClientInterface;
use Domain\Gateway\DTOs\GatewayMessage;
use Domain\Gateway\DTOs\GatewayResponse;
use Domain\Gateway\Exceptions\GatewayTimeoutException;
use Domain\Gateway\Exceptions\ProviderException;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Redis;

/**
 * Service for Redis-based gateway communication.
 *
 * @category Services
 */
final class RedisGatewayClient implements GatewayClientInterface
{
    /**
     * Send a message and wait for response (blocking).
     *
     * Responsibility:
     * 1. Publish message to the domain stream (XADD)
     * 2. Wait for response on the response stream (XREAD blocking)
     * 3. Parse response and return DTO
     * 4. Clean up response stream after reading
     * 5. Throw appropriate exceptions
     *
     * @throws GatewayTimeoutException If timeout expires
     * @throws ProviderException If provider returns an error
     */
    public function send(GatewayMessage $message, int $timeoutSeconds = 180): GatewayResponse
    {
        // 1. Publish to request stream
        $streamName = $message->domain->streamName();

        /** @var \Illuminate\Redis\Connections\Connection $redis */
        $redis = Redis::connection(config('gateway.redis.connection', 'default'));

        $this->xadd($redis, $streamName, $this->normalizeStreamFields($message->toArray()));

        // 2. Wait on response stream
        $responseStream = config('gateway.streams.ai_response_prefix', 'ai.run.response:').$message->correlationId;

        $blockMs = $timeoutSeconds * 1000;
        $result = $this->xread($redis, $responseStream, $blockMs);

        if (empty($result) || ! is_array($result)) {
            throw new GatewayTimeoutException(
                message: "Gateway did not respond within {$timeoutSeconds}s",
                correlationId: $message->correlationId,
                timeoutDuration: $timeoutSeconds,
            );
        }

        // 3. Parse response
        $responseData = $this->parseStreamResponse($result, $responseStream);
        $response = GatewayResponse::fromArray($responseData);

        // 4. Clean up response stream
        $redis->del($responseStream);

        // 5. Check for provider error
        if ($response->failed() && $response->error !== null) {
            throw ProviderException::fromGatewayError(
                $response->error,
                $message->correlationId
            );
        }

        return $response;
    }

    /**
     * Send a message without waiting for response (fire-and-forget).
     */
    public function dispatch(GatewayMessage $message): string
    {
        $streamName = $message->domain->streamName();

        /** @var \Illuminate\Redis\Connections\Connection $redis */
        $redis = Redis::connection(config('gateway.redis.connection', 'default'));

        $this->xadd($redis, $streamName, $this->normalizeStreamFields($message->toArray()));

        return $message->correlationId;
    }

    /**
     * Parse the stream response from executeRaw XREAD result.
     *
     * executeRaw returns the native Redis RESP format:
     * [[streamName, [[messageId, [field, value, field, value, ...]]]]]
     *
     * @param  array<int, mixed>  $result
     * @return array<string, mixed>
     */
    private function parseStreamResponse(array $result, string $streamName): array
    {
        // Navigate raw XREAD structure: [0][1][0][1] = flat [field, value, ...]
        $streamData = $result[0] ?? null;
        if (! is_array($streamData) || ($streamData[0] ?? '') !== $streamName) {
            return [];
        }

        $messages = $streamData[1] ?? [];
        if (empty($messages) || ! is_array($messages)) {
            return [];
        }

        $firstMessage = $messages[0] ?? [];
        $flatFields = $firstMessage[1] ?? [];
        if (! is_array($flatFields)) {
            return [];
        }

        // Convert flat [key, val, key, val, ...] to associative array
        $messageData = [];
        for ($i = 0, $len = count($flatFields); $i < $len; $i += 2) {
            $messageData[(string) $flatFields[$i]] = $flatFields[$i + 1] ?? '';
        }

        // Parse JSON fields back to arrays
        $parsed = [
            'correlationId' => $messageData['correlationId'] ?? $messageData['correlation_id'] ?? '',
            'timestamp' => $messageData['timestamp'] ?? now()->toIso8601String(),
            'success' => filter_var($messageData['success'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        if (! empty($messageData['data'])) {
            $parsed['data'] = is_string($messageData['data'])
                ? json_decode($messageData['data'], true, 512, JSON_THROW_ON_ERROR)
                : $messageData['data'];
        }

        if (! empty($messageData['error'])) {
            $parsed['error'] = is_string($messageData['error'])
                ? json_decode($messageData['error'], true, 512, JSON_THROW_ON_ERROR)
                : $messageData['error'];
        }

        return $parsed;
    }

    /**
     * Normalize Redis stream fields to scalar strings accepted by XADD.
     *
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

            $normalized[$key] = json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $normalized;
    }

    /**
     * Publish fields to a Redis stream with compatibility for Predis and PhpRedis.
     *
     * @param  array<string, string>  $fields
     */
    private function xadd(Connection $redis, string $streamName, array $fields): void
    {
        if ($redis instanceof PredisConnection) {
            $arguments = ['XADD', $streamName, '*'];

            foreach ($fields as $key => $value) {
                $arguments[] = (string) $key;
                $arguments[] = (string) $value;
            }

            $redis->client()->executeRaw($arguments);

            return;
        }

        $redis->xadd($streamName, '*', $fields);
    }

    /**
     * Read from a Redis stream in a Predis/PhpRedis compatible way.
     */
    private function xread(Connection $redis, string $responseStream, int $blockMs): mixed
    {
        if ($redis instanceof PredisConnection) {
            return $redis->client()->executeRaw([
                'XREAD',
                'BLOCK', (string) $blockMs,
                'STREAMS',
                $responseStream,
                '0-0',
            ]);
        }

        return $redis->command('XREAD', [
            'BLOCK', (string) $blockMs, 'STREAMS', $responseStream, '0-0',
        ]);
    }
}
