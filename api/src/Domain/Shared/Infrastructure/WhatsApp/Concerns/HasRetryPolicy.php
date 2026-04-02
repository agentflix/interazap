<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\WhatsApp\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Trait para aplicar política de retry em adapters.
 */
trait HasRetryPolicy
{
    protected int $maxRetries = 3;

    protected int $timeoutSeconds = 30;

    protected int $retryDelayMs = 1000;

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

    protected function getRetryDelayMs(): int
    {
        // Allow tests to override retry delay via config
        return (int) config('services.http_retry_delay_ms', $this->retryDelayMs);
    }

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
