<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Redis;

/**
 * Publica eventos `ai.run.request` no Redis Stream consumido pelo gateway.
 *
 * Centraliza a lógica de XADD e serialização para que tanto o caminho de
 * dispatch inicial quanto o de delegação usem o mesmo pipeline rápido.
 * Suporta conexões Predis e PhpRedis nativo.
 */
final class AutopilotRunStreamPublisher
{
    private const STREAM_NAME = 'ai.run.request';

    /**
     * Publica um evento no Redis Stream `ai.run.request` via XADD.
     *
     * Suporta tanto conexões Predis (executeRaw) quanto PhpRedis nativo (xadd).
     *
     * @param  array<string, mixed>  $streamPayload  Campos do evento a publicar.
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
     * Normaliza os campos do payload para o formato string exigido pelo Redis Stream.
     *
     * Converte bool → '1'/'0', int/float → string, arrays → JSON, null → ''.
     *
     * @param  array<string, mixed>  $fields  Campos originais do payload.
     * @return array<string, string> Campos com valores convertidos para string.
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
