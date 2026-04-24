<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Redis;

/**
 * Publica eventos `ai.run.request` no Redis Stream consumido pelo gateway.
 *
 * Centraliza a lógica de XADD + serialização para que tanto o caminho de
 * dispatch inicial (DispatchAutopilotRunJob) quanto o de delegação
 * (AiAgentDelegationService) usem o mesmo pipeline rápido e instrumentado.
 */
final class AutopilotRunStreamPublisher
{
    private const STREAM_NAME = 'ai.run.request';

    /**
     * @param  array<string, mixed>  $streamPayload
     */
    public function publish(array $streamPayload): void
    {
        /** @var Connection $redis */
        $redis = Redis::connection(config('gateway.redis.connection', 'gateway'));
        $fields = $this->normalizeStreamFields($streamPayload);

        if ($redis instanceof PredisConnection) {
            $arguments = ['XADD', self::STREAM_NAME, '*'];

            foreach ($fields as $key => $value) {
                $arguments[] = (string) $key;
                $arguments[] = $value;
            }

            $redis->client()->executeRaw($arguments);

            return;
        }

        $redis->xadd(self::STREAM_NAME, '*', $fields);
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

            $normalized[$key] = json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $normalized;
    }
}
