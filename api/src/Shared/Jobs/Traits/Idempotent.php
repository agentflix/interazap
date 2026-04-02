<?php

declare(strict_types=1);

namespace Shared\Jobs\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Trait providing idempotent job execution.
 *
 * Ensures jobs are only processed once, even if dispatched multiple times.
 * Uses cache to track processed job keys with configurable TTL.
 */
// @phpstan-ignore-next-line
trait Idempotent
{
    /**
     * Cache TTL in seconds for idempotency keys.
     * Default: 7 days
     */
    protected int $idempotencyTtl = 604800;

    /**
     * Cache prefix for idempotency keys.
     */
    protected string $idempotencyPrefix = 'idempotent';

    /**
     * Execute the job with idempotency check.
     * This method should be called from handle().
     */
    protected function executeIdempotent(): void
    {
        $key = $this->getIdempotencyKey();

        if ($this->hasBeenProcessed($key)) {
            Log::info('[Idempotent] Job already processed, skipping', [
                'key' => $key,
                'job' => static::class,
            ]);

            return;
        }

        try {
            $this->process();
            $this->markAsProcessed($key);
        } catch (\Throwable $e) {
            // Don't mark as processed if job failed
            throw $e;
        }
    }

    /**
     * Generate the idempotency key for this job.
     * Override this method to customize key generation.
     */
    protected function getIdempotencyKey(): string
    {
        $payload = $this->getIdempotencyPayload();

        return sprintf(
            '%s:%s:%s',
            $this->idempotencyPrefix,
            class_basename(static::class),
            md5(serialize($payload))
        );
    }

    /**
     * Get the payload used to generate idempotency key.
     * Override this method to customize payload.
     *
     * @return array<string, mixed>
     */
    abstract protected function getIdempotencyPayload(): array;

    /**
     * Process the actual job logic.
     * Implement this method with your job's business logic.
     */
    abstract protected function process(): void;

    /**
     * Check if the job has already been processed.
     */
    protected function hasBeenProcessed(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * Mark the job as processed.
     */
    protected function markAsProcessed(string $key): void
    {
        Cache::put($key, [
            'processed_at' => now()->toIso8601String(),
            'job' => static::class,
        ], $this->idempotencyTtl);

        Log::debug('[Idempotent] Job marked as processed', [
            'key' => $key,
            'ttl' => $this->idempotencyTtl,
        ]);
    }

    /**
     * Force reprocessing by clearing the idempotency key.
     */
    protected function clearIdempotencyKey(): void
    {
        $key = $this->getIdempotencyKey();
        Cache::forget($key);

        Log::info('[Idempotent] Idempotency key cleared', [
            'key' => $key,
        ]);
    }

    /**
     * Set the TTL for idempotency keys.
     */
    protected function setIdempotencyTtl(int $seconds): self
    {
        $this->idempotencyTtl = $seconds;

        return $this;
    }

    /**
     * Get the current TTL setting.
     */
    protected function getIdempotencyTtl(): int
    {
        return $this->idempotencyTtl;
    }
}
