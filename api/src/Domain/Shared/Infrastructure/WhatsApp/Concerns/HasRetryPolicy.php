<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\WhatsApp\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Trait para aplicar política de retry com backoff exponencial em adapters de WhatsApp.
 *
 * Configura o cliente HTTP com número máximo de tentativas, delay inicial e
 * critério de retentar somente em erros de conexão, 5xx e 429 (rate limit).
 */
trait HasRetryPolicy
{
    protected int $maxRetries = 3;

    protected int $timeoutSeconds = 30;

    protected int $retryDelayMs = 1000;

    /**
     * Cria e configura o cliente HTTP com política de retry e backoff exponencial.
     *
     * @return PendingRequest Cliente HTTP configurado com timeout e retry.
     */
    protected function createHttpClient(): PendingRequest
    {
        $delayMs = $this->getRetryDelayMs();

        return Http::timeout($this->timeoutSeconds)
            ->retry(
                times: $this->maxRetries,
                sleepMilliseconds: fn (int $attempt) => $delayMs * (2 ** ($attempt - 1)),
                when: fn (\Exception $exception) => $this->shouldRetry($exception),
                throw: true,
            )
            ->withOptions([
                'connect_timeout' => 10,
            ]);
    }

    /**
     * Retorna o delay de retry em milissegundos, com override via config.
     *
     * @return int Delay em milissegundos.
     */
    protected function getRetryDelayMs(): int
    {
        // Allow tests to override retry delay via config
        return (int) config('services.http_retry_delay_ms', $this->retryDelayMs);
    }

    /**
     * Determina se a exceção justifica uma nova tentativa.
     *
     * Retorna verdadeiro para erros de conexão, 5xx e 429 (rate limit).
     *
     * @param  \Exception  $exception  Exceção capturada na requisição.
     * @return bool Verdadeiro se deve tentar novamente.
     */
    protected function shouldRetry(\Exception $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            if ($status >= 500) {
                return true;
            }

            if ($status === 429) {
                return true;
            }

            return false;
        }

        return false;
    }
}
