<?php

declare(strict_types=1);

namespace Tests\Unit\Ai\Jobs;

use Domain\Ai\Enums\AiProviderType;
use Domain\Ai\Jobs\ProcessAIResponseJob;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Shared\Jobs\Middleware\RateLimitedJob;
use Tests\TestCase;

class ProcessAIResponseJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    #[Test]
    public function it_uses_retryable_with_backoff_trait(): void
    {
        $job = new ProcessAIResponseJob(
            runId: 'run-123',
            tenantId: 'tenant-123',
            prompt: 'Test prompt',
        );

        // Default tries from trait
        $this->assertSame(5, $job->tries);
        // Custom timeout for AI
        $this->assertSame(180, $job->timeout);
        // Custom maxExceptions
        $this->assertSame(3, $job->maxExceptions);
    }

    #[Test]
    public function it_includes_rate_limited_middleware(): void
    {
        $job = new ProcessAIResponseJob(
            runId: 'run-123',
            tenantId: 'tenant-123',
            prompt: 'Test prompt',
        );

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimitedJob::class, $middleware[0]);
    }

    #[Test]
    public function it_has_correct_unique_id(): void
    {
        $job = new ProcessAIResponseJob(
            runId: 'run-123',
            tenantId: 'tenant-123',
            prompt: 'Test prompt',
        );

        $this->assertSame('ai-run:run-123', $job->uniqueId());
    }

    #[Test]
    public function it_has_appropriate_tags(): void
    {
        $job = new ProcessAIResponseJob(
            runId: 'run-123',
            tenantId: 'tenant-123',
            prompt: 'Test prompt',
            provider: AiProviderType::OPENAI,
        );

        $tags = $job->tags();

        $this->assertContains('ai', $tags);
        $this->assertContains('tenant:tenant-123', $tags);
        $this->assertContains('provider:openai', $tags);
        $this->assertContains('ai:completion', $tags);
    }

    #[Test]
    public function it_can_be_dispatched(): void
    {
        dispatch(new \Domain\Ai\Jobs\ProcessAIResponseJob(runId: 'run-123', tenantId: 'tenant-123', prompt: 'Test prompt'));

        Queue::assertPushed(ProcessAIResponseJob::class);
    }

    #[Test]
    public function it_uses_ai_specific_backoff_delays(): void
    {
        $job = new ProcessAIResponseJob(
            runId: 'run-123',
            tenantId: 'tenant-123',
            prompt: 'Test prompt',
        );

        // AI backoff via getBackoffDelays(): [15, 45, 120, 300, 600]
        $backoffWithJitter = $job->backoff();

        $this->assertCount(5, $backoffWithJitter);

        // Each delay should be within ±20% of the original
        $originalDelays = [15, 45, 120, 300, 600];
        foreach ($backoffWithJitter as $index => $delay) {
            $original = $originalDelays[$index];
            $minExpected = (int) ($original * 0.8);
            $maxExpected = (int) ($original * 1.2);

            $this->assertGreaterThanOrEqual($minExpected, $delay);
            $this->assertLessThanOrEqual($maxExpected, $delay);
        }
    }

    #[Test]
    public function it_has_extended_timeout_for_ai_responses(): void
    {
        $job = new ProcessAIResponseJob(
            runId: 'run-123',
            tenantId: 'tenant-123',
            prompt: 'Test prompt',
        );

        // AI timeout should be 180 seconds (3 minutes)
        $this->assertSame(180, $job->timeout);
    }

    #[Test]
    public function it_accepts_custom_options(): void
    {
        $job = new ProcessAIResponseJob(
            runId: 'run-123',
            tenantId: 'tenant-123',
            prompt: 'Test prompt',
            provider: AiProviderType::OPENAI,
            options: [
                'model' => 'gpt-4o',
                'temperature' => 0.5,
                'max_tokens' => 4096,
            ],
        );

        $this->assertInstanceOf(ProcessAIResponseJob::class, $job);
    }

    #[Test]
    public function it_accepts_context_data(): void
    {
        $job = new ProcessAIResponseJob(
            runId: 'run-123',
            tenantId: 'tenant-123',
            prompt: 'Test prompt',
            context: [
                'conversation_history' => [],
                'system_prompt' => 'You are a helpful assistant',
            ],
        );

        $this->assertInstanceOf(ProcessAIResponseJob::class, $job);
    }
}
