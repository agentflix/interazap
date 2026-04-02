<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Shared\Jobs\Middleware\RateLimitedJob;
use Tests\TestCase;

class RateLimitedJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('job:TestJob');
        RateLimiter::clear('job:whatsapp');
        RateLimiter::clear('job:ai');
        RateLimiter::clear('job:payment');
    }

    #[Test]
    public function it_allows_job_execution_within_rate_limit(): void
    {
        $middleware = new RateLimitedJob(maxAttempts: 5);
        $job = $this->createMockJob();

        $executed = false;
        $middleware->handle($job, function () use (&$executed): true {
            $executed = true;

            return true;
        });

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_blocks_job_when_rate_limit_exceeded(): void
    {
        $middleware = new RateLimitedJob(maxAttempts: 2);
        $job = $this->createMockJob();

        // Execute twice to hit limit
        $middleware->handle($job, fn (): true => true);
        $middleware->handle($job, fn (): true => true);

        // Third attempt should be blocked
        $executed = false;
        $result = $middleware->handle($job, function () use (&$executed): true {
            $executed = true;

            return true;
        });

        $this->assertFalse($executed);
        $this->assertNull($result);
    }

    #[Test]
    public function it_creates_middleware_with_static_make_method(): void
    {
        $middleware = RateLimitedJob::make(
            limiterName: 'custom',
            maxAttempts: 100,
            decaySeconds: 120,
            releaseAfter: 60
        );

        $this->assertInstanceOf(RateLimitedJob::class, $middleware);
    }

    #[Test]
    public function it_creates_whatsapp_rate_limiter(): void
    {
        $middleware = RateLimitedJob::forWhatsApp();

        $this->assertInstanceOf(RateLimitedJob::class, $middleware);
    }

    #[Test]
    public function it_creates_ai_rate_limiter(): void
    {
        $middleware = RateLimitedJob::forAI();

        $this->assertInstanceOf(RateLimitedJob::class, $middleware);
    }

    #[Test]
    public function it_creates_payment_rate_limiter(): void
    {
        $middleware = RateLimitedJob::forPayment();

        $this->assertInstanceOf(RateLimitedJob::class, $middleware);
    }

    #[Test]
    public function it_uses_tenant_id_in_rate_limit_key_when_available(): void
    {
        $middleware = new RateLimitedJob(maxAttempts: 1);

        $jobWithTenant = new class implements ShouldQueue
        {
            use Dispatchable, InteractsWithQueue, Queueable;

            private string $tenantId = 'tenant-123';

            public function release(int $delay = 0): void {}
        };

        // First call for tenant-123
        $middleware->handle($jobWithTenant, fn (): true => true);

        // Create another job with different tenant
        $jobWithOtherTenant = new class implements ShouldQueue
        {
            use Dispatchable, InteractsWithQueue, Queueable;

            private string $tenantId = 'tenant-456';

            public function release(int $delay = 0): void {}
        };

        // Should succeed because it's a different tenant
        $executed = false;
        $middleware->handle($jobWithOtherTenant, function () use (&$executed): true {
            $executed = true;

            return true;
        });

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_uses_custom_limiter_name(): void
    {
        $middleware = new RateLimitedJob(limiterName: 'custom-limiter', maxAttempts: 1);
        $job = $this->createMockJob();

        $middleware->handle($job, fn (): true => true);

        // Check that the custom key was used
        $this->assertTrue(RateLimiter::tooManyAttempts('job:custom-limiter', 1));
    }

    #[Test]
    public function it_hits_rate_limiter_on_execution(): void
    {
        $middleware = new RateLimitedJob(limiterName: 'decay-test', maxAttempts: 5, decaySeconds: 60);
        $job = $this->createMockJob();

        $middleware->handle($job, fn (): true => true);

        // Should have recorded the hit
        $this->assertSame(4, RateLimiter::remaining('job:decay-test', 5));
    }

    #[Test]
    public function whatsapp_limiter_has_correct_configuration(): void
    {
        $middleware = RateLimitedJob::forWhatsApp();
        $job = $this->createMockJob();

        // Should allow 30 calls
        for ($i = 0; $i < 30; $i++) {
            $executed = false;
            $middleware->handle($job, function () use (&$executed): true {
                $executed = true;

                return true;
            });
            $this->assertTrue($executed, "Call $i should succeed");
        }

        // 31st should be blocked
        $blockedJob = new class implements ShouldQueue
        {
            use Dispatchable, InteractsWithQueue, Queueable;

            public bool $wasReleased = false;

            public function release(int $delay = 0): void
            {
                $this->wasReleased = true;
            }
        };

        $executed = false;
        $middleware->handle($blockedJob, function () use (&$executed): true {
            $executed = true;

            return true;
        });

        $this->assertFalse($executed);
        $this->assertTrue($blockedJob->wasReleased);
    }

    private function createMockJob(): object
    {
        return new class
        {
            public function release(int $delay = 0): void {}
        };
    }
}
