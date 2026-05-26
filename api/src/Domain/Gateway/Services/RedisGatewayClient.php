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
 * Implementação do GatewayClientInterface que usa Redis Streams para comunicação com o gateway NestJS.
 *
 * Publica mensagens via XADD no stream do domínio e aguarda a resposta em um stream
 * de resposta dedicado por correlationId. Suporta Predis e PhpRedis.
 */
final class RedisGatewayClient implements GatewayClientInterface
{
    /**
     * Envia uma mensagem ao stream do domínio e aguarda a resposta (bloqueante).
     *
     * Fluxo:
     * 1. Publica a mensagem no stream do domínio via XADD
     * 2. Aguarda resposta no stream dedicado ao correlationId via XREAD bloqueante
     * 3. Parseia a resposta e retorna o DTO
     * 4. Remove o stream de resposta após a leitura
     * 5. Lança exceções adequadas em caso de timeout ou erro do provider
     *
     * @param  GatewayMessage  $message  Mensagem a enviar
     * @param  int  $timeoutSeconds  Tempo máximo de espera em segundos
     * @return GatewayResponse Resposta recebida do gateway
     *
     * @throws GatewayTimeoutException Quando o tempo limite expira
     * @throws ProviderException Quando o provider retorna um erro
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
     * Publica uma mensagem no stream do domínio sem aguardar resposta (fire-and-forget).
     *
     * @param  GatewayMessage  $message  Mensagem a despachar
     * @return string O correlationId gerado para rastreamento posterior
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
     * Parseia a resposta bruta do XREAD no formato Redis RESP.
     *
     * O formato nativo é: [[streamName, [[messageId, [field, value, field, value, ...]]]]]
     * O resultado é convertido em array associativo com campos JSON decodificados.
     *
     * @param  array<int, mixed>  $result  Resultado bruto do XREAD
     * @param  string  $streamName  Nome do stream para validação
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
     * Normaliza os campos do stream para strings escalares aceitas pelo XADD.
     *
     * Arrays e objetos são serializados como JSON; booleanos convertidos para "1"/"0".
     *
     * @param  array<string, mixed>  $fields  Campos a normalizar
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
     * Publica campos em um Redis Stream com compatibilidade para Predis e PhpRedis.
     *
     * @param  Connection  $redis  Conexão Redis ativa
     * @param  string  $streamName  Nome do stream de destino
     * @param  array<string, string>  $fields  Campos a publicar
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
     * Lê mensagens de um Redis Stream de forma bloqueante, compatível com Predis e PhpRedis.
     *
     * @param  Connection  $redis  Conexão Redis ativa
     * @param  string  $responseStream  Nome do stream de resposta
     * @param  int  $blockMs  Tempo máximo de bloqueio em milissegundos
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
