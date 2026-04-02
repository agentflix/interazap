<?php

declare(strict_types=1);

namespace Shared\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Middleware for rate limiting job execution.
 *
 * This middleware allows jobs to be rate-limited by class name,
 * preventing overwhelming external services or databases.
 */
final class RateLimitedJob
{
    /**
     * Create a new middleware instance.
     *
     * @param  string  $limiterName  The name of the rate limiter.
     * @param  int  $maxAttempts  Maximum attempts per time window.
     * @param  int  $decaySeconds  Time window in seconds.
     * @param  int  $releaseAfter  Seconds to wait before retry when limited.
     */
    public function __construct(
        protected string $limiterName = '',
        protected int $maxAttempts = 10,
        protected int $decaySeconds = 60,
        protected int $releaseAfter = 30,
    ) {}

    /**
     * Process the queued job.
     *
     * @param  mixed  $job  The job instance.
     * @param  Closure  $next  The next middleware.
     */
    public function handle(mixed $job, Closure $next): mixed
    {
        $key = $this->resolveLimiterKey($job);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            logger()->warning('Job rate limited', [
                'job' => get_class($job),
                'key' => $key,
                'retry_after' => $retryAfter,
            ]);

            // Release the job back to the queue with delay
            $job->release($this->releaseAfter);

            return null;
        }

        RateLimiter::hit($key, $this->decaySeconds);

        return $next($job);
    }

    /**
     * Resolve the rate limiter key for the job.
     */
    protected function resolveLimiterKey(mixed $job): string
    {
        $baseName = $this->limiterName !== ''
            ? $this->limiterName
            : class_basename($job);

        // Include tenant_id if available for per-tenant limiting
        $tenantId = $this->extractTenantId($job);

        return $tenantId !== null
            ? "job:{$baseName}:{$tenantId}"
            : "job:{$baseName}";
    }

    /**
     * Extract tenant ID from job if available.
     */
    protected function extractTenantId(mixed $job): ?string
    {
        // Check for tenantId property via reflection
        if (property_exists($job, 'tenantId')) {
            $reflection = new \ReflectionProperty($job, 'tenantId');
            $reflection->setAccessible(true);

            return (string) $reflection->getValue($job);
        }

        // Check for tenant_id property
        if (property_exists($job, 'tenant_id')) {
            $reflection = new \ReflectionProperty($job, 'tenant_id');
            $reflection->setAccessible(true);

            return (string) $reflection->getValue($job);
        }

        return null;
    }

    /**
     * Create a new middleware instance with custom configuration.
     */
    public static function make(
        string $limiterName = '',
        int $maxAttempts = 10,
        int $decaySeconds = 60,
        int $releaseAfter = 30,
    ): static {
        return new self($limiterName, $maxAttempts, $decaySeconds, $releaseAfter);
    }

    /**
     * Create a rate limiter for WhatsApp API calls.
     * WhatsApp has strict rate limits, so we use conservative values.
     */
    public static function forWhatsApp(): static
    {
        return new static(
            limiterName: 'whatsapp',
            maxAttempts: 30,
            decaySeconds: 60,
            releaseAfter: 60,
        );
    }

    /**
     * Create a rate limiter for AI/LLM API calls.
     * AI APIs often have token-based limits, so we use moderate values.
     */
    public static function forAI(): static
    {
        return new static(
            limiterName: 'ai',
            maxAttempts: 20,
            decaySeconds: 60,
            releaseAfter: 30,
        );
    }

    /**
     * Create a rate limiter for payment processing.
     * Payment APIs are sensitive, so we use strict values.
     */
    public static function forPayment(): static
    {
        return new static(
            limiterName: 'payment',
            maxAttempts: 5,
            decaySeconds: 60,
            releaseAfter: 120,
        );
    }
}
