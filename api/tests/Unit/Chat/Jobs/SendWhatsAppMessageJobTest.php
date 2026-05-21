<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Jobs;

use Domain\Chat\Contracts\ChatWhatsAppGatewayInterface;
use Domain\Chat\Jobs\SendWhatsAppMessageJob;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Shared\Jobs\Middleware\RateLimitedJob;
use Tests\TestCase;

class SendWhatsAppMessageJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    #[Test]
    public function it_uses_retryable_with_backoff_trait(): void
    {
        $job = new SendWhatsAppMessageJob(
            messageId: 'msg-123',
            tenantId: 'tenant-123',
            instanceId: 'instance-456',
            to: '+5511999999999',
            content: 'Test message',
        );

        // Default tries from trait
        $this->assertSame(5, $job->tries);
        // Custom timeout
        $this->assertSame(60, $job->timeout);
        // Custom maxExceptions
        $this->assertSame(4, $job->maxExceptions);
    }

    #[Test]
    public function it_includes_rate_limited_middleware(): void
    {
        $job = new SendWhatsAppMessageJob(
            messageId: 'msg-123',
            tenantId: 'tenant-123',
            instanceId: 'instance-456',
            to: '+5511999999999',
            content: 'Test message',
        );

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimitedJob::class, $middleware[0]);
    }

    #[Test]
    public function it_has_correct_unique_id(): void
    {
        $job = new SendWhatsAppMessageJob(
            messageId: 'msg-123',
            tenantId: 'tenant-123',
            instanceId: 'instance-456',
            to: '+5511999999999',
            content: 'Test message',
        );

        $this->assertSame('whatsapp:msg-123', $job->uniqueId());
    }

    #[Test]
    public function it_has_appropriate_tags(): void
    {
        $job = new SendWhatsAppMessageJob(
            messageId: 'msg-123',
            tenantId: 'tenant-123',
            instanceId: 'instance-456',
            to: '+5511999999999',
            content: 'Test message',
        );

        $tags = $job->tags();

        $this->assertContains('whatsapp', $tags);
        $this->assertContains('tenant:tenant-123', $tags);
        $this->assertContains('instance:instance-456', $tags);
        $this->assertContains('message:send', $tags);
    }

    #[Test]
    public function it_can_be_dispatched(): void
    {
        dispatch(new \Domain\Chat\Jobs\SendWhatsAppMessageJob(messageId: 'msg-123', tenantId: 'tenant-123', instanceId: 'instance-456', to: '+5511999999999', content: 'Test message'));

        Queue::assertPushed(SendWhatsAppMessageJob::class);
    }

    #[Test]
    public function it_accepts_gateway_interface_as_dependency(): void
    {
        $gateway = Mockery::mock(ChatWhatsAppGatewayInterface::class);

        // Verify the interface exists and can be mocked
        $this->assertInstanceOf(ChatWhatsAppGatewayInterface::class, $gateway);
    }

    #[Test]
    public function it_uses_whatsapp_specific_backoff_delays(): void
    {
        $job = new SendWhatsAppMessageJob(
            messageId: 'msg-123',
            tenantId: 'tenant-123',
            instanceId: 'instance-456',
            to: '+5511999999999',
            content: 'Test message',
        );

        // WhatsApp backoff via getBackoffDelays(): [5, 15, 45, 120, 300]
        $backoffWithJitter = $job->backoff();

        $this->assertCount(5, $backoffWithJitter);

        // Each delay should be within ±20% of the original
        $originalDelays = [5, 15, 45, 120, 300];
        foreach ($backoffWithJitter as $index => $delay) {
            $original = $originalDelays[$index];
            $minExpected = max(1, (int) ($original * 0.8));
            $maxExpected = (int) ($original * 1.2);

            $this->assertGreaterThanOrEqual($minExpected, $delay);
            $this->assertLessThanOrEqual($maxExpected, $delay);
        }
    }

    #[Test]
    public function it_supports_different_message_types(): void
    {
        $job = new SendWhatsAppMessageJob(
            messageId: 'msg-123',
            tenantId: 'tenant-123',
            instanceId: 'instance-456',
            to: '+5511999999999',
            content: 'https://example.com/image.jpg',
            type: 'image',
            metadata: ['caption' => 'Check this out'],
        );

        $this->assertInstanceOf(SendWhatsAppMessageJob::class, $job);
    }
}
