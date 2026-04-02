<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Jobs;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Shared\Jobs\Traits\Idempotent;
use Tests\TestCase;

class IdempotentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function it_processes_job_on_first_execution(): void
    {
        $job = new TestIdempotentJob('test-123', 100);

        $job->handle();

        $this->assertTrue($job->wasProcessed);
        $this->assertSame(1, $job->processCount);
    }

    #[Test]
    public function it_skips_processing_on_duplicate_execution(): void
    {
        $job1 = new TestIdempotentJob('test-123', 100);
        $job2 = new TestIdempotentJob('test-123', 100);

        $job1->handle();
        $job2->handle();

        $this->assertTrue($job1->wasProcessed);
        $this->assertFalse($job2->wasProcessed);
    }

    #[Test]
    public function it_processes_jobs_with_different_payloads(): void
    {
        $job1 = new TestIdempotentJob('test-123', 100);
        $job2 = new TestIdempotentJob('test-456', 100);

        $job1->handle();
        $job2->handle();

        $this->assertTrue($job1->wasProcessed);
        $this->assertTrue($job2->wasProcessed);
    }

    #[Test]
    public function it_generates_unique_keys_for_different_payloads(): void
    {
        $job1 = new TestIdempotentJob('test-123', 100);
        $job2 = new TestIdempotentJob('test-123', 200);

        $key1 = $job1->getPublicIdempotencyKey();
        $key2 = $job2->getPublicIdempotencyKey();

        $this->assertNotEquals($key1, $key2);
    }

    #[Test]
    public function it_can_clear_idempotency_key(): void
    {
        $job1 = new TestIdempotentJob('test-123', 100);
        $job1->handle();

        $this->assertTrue($job1->wasProcessed);

        // Clear the key
        $job1->publicClearIdempotencyKey();

        // Create new job with same payload
        $job2 = new TestIdempotentJob('test-123', 100);
        $job2->handle();

        $this->assertTrue($job2->wasProcessed);
    }

    #[Test]
    public function it_does_not_mark_as_processed_on_failure(): void
    {
        $job1 = new TestIdempotentJob('test-123', 100, shouldFail: true);

        try {
            $job1->handle();
        } catch (\Exception) {
            // Expected
        }

        // Create new job with same payload - should process
        $job2 = new TestIdempotentJob('test-123', 100);
        $job2->handle();

        $this->assertTrue($job2->wasProcessed);
    }

    #[Test]
    public function it_uses_custom_ttl(): void
    {
        $job = new TestIdempotentJob('test-123', 100);
        $job->setPublicIdempotencyTtl(60);

        $this->assertSame(60, $job->getPublicIdempotencyTtl());
    }

    #[Test]
    public function it_includes_job_class_in_key(): void
    {
        $job = new TestIdempotentJob('test-123', 100);
        $key = $job->getPublicIdempotencyKey();

        $this->assertStringContainsString('TestIdempotentJob', $key);
    }
}

/**
 * Test implementation of Idempotent trait.
 */
class TestIdempotentJob
{
    use Idempotent;

    public bool $wasProcessed = false;

    public int $processCount = 0;

    public function __construct(
        private readonly string $transactionId,
        private readonly int $amount,
        private readonly bool $shouldFail = false,
    ) {}

    public function handle(): void
    {
        $this->executeIdempotent();
    }

    protected function getIdempotencyPayload(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
        ];
    }

    protected function process(): void
    {
        if ($this->shouldFail) {
            throw new \Exception('Intentional failure');
        }

        $this->wasProcessed = true;
        $this->processCount++;
    }

    public function getPublicIdempotencyKey(): string
    {
        return $this->getIdempotencyKey();
    }

    public function publicClearIdempotencyKey(): void
    {
        $this->clearIdempotencyKey();
    }

    public function setPublicIdempotencyTtl(int $seconds): self
    {
        return $this->setIdempotencyTtl($seconds);
    }

    public function getPublicIdempotencyTtl(): int
    {
        return $this->getIdempotencyTtl();
    }
}
