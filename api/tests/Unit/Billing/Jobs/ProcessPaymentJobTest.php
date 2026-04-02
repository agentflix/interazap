<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Jobs;

use Domain\Billing\Jobs\ProcessPaymentJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Shared\Jobs\Middleware\RateLimitedJob;
use Tests\TestCase;

class ProcessPaymentJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();
    }

    #[Test]
    public function it_uses_retryable_with_backoff_trait(): void
    {
        $job = new ProcessPaymentJob(
            transactionId: 'txn-123',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
        );

        // Default tries from trait
        $this->assertSame(5, $job->tries);
        // Custom timeout
        $this->assertSame(120, $job->timeout);
        // Conservative maxExceptions for payment safety
        $this->assertSame(2, $job->maxExceptions);
    }

    #[Test]
    public function it_uses_idempotent_trait(): void
    {
        $job = new ProcessPaymentJob(
            transactionId: 'txn-123',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
        );

        // Idempotent trait should set TTL
        $this->assertTrue(method_exists($job, 'getIdempotencyPayload'));
    }

    #[Test]
    public function it_includes_rate_limited_middleware(): void
    {
        $job = new ProcessPaymentJob(
            transactionId: 'txn-123',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
        );

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimitedJob::class, $middleware[0]);
    }

    #[Test]
    public function it_has_correct_unique_id(): void
    {
        $job = new ProcessPaymentJob(
            transactionId: 'txn-123',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
        );

        $this->assertSame('payment:txn-123', $job->uniqueId());
    }

    #[Test]
    public function it_has_appropriate_tags(): void
    {
        $job = new ProcessPaymentJob(
            transactionId: 'txn-123',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
            paymentMethod: 'pix',
        );

        $tags = $job->tags();

        $this->assertContains('payment', $tags);
        $this->assertContains('tenant:tenant-123', $tags);
        $this->assertContains('invoice:inv-123', $tags);
        $this->assertContains('method:pix', $tags);
    }

    #[Test]
    public function it_can_be_dispatched(): void
    {
        dispatch(new \Domain\Billing\Jobs\ProcessPaymentJob(transactionId: 'txn-123', tenantId: 'tenant-123', invoiceId: 'inv-123', amount: 10000));

        Queue::assertPushed(ProcessPaymentJob::class);
    }

    #[Test]
    public function it_uses_payment_specific_backoff_delays(): void
    {
        $job = new ProcessPaymentJob(
            transactionId: 'txn-123',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
        );

        // Payment backoff via getBackoffDelays(): [30, 120, 300, 600, 1800]
        $backoffWithJitter = $job->backoff();

        $this->assertCount(5, $backoffWithJitter);

        // Each delay should be within ±20% of the original
        $originalDelays = [30, 120, 300, 600, 1800];
        foreach ($backoffWithJitter as $index => $delay) {
            $original = $originalDelays[$index];
            $minExpected = (int) ($original * 0.8);
            $maxExpected = (int) ($original * 1.2);

            $this->assertGreaterThanOrEqual($minExpected, $delay);
            $this->assertLessThanOrEqual($maxExpected, $delay);
        }
    }

    #[Test]
    public function it_has_conservative_max_exceptions(): void
    {
        $job = new ProcessPaymentJob(
            transactionId: 'txn-123',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
        );

        // Payment should fail faster to prevent duplicate charges
        $this->assertSame(2, $job->maxExceptions);
    }

    #[Test]
    public function it_supports_different_payment_methods(): void
    {
        $pixJob = new ProcessPaymentJob(
            transactionId: 'txn-pix',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
            paymentMethod: 'pix',
        );

        $cardJob = new ProcessPaymentJob(
            transactionId: 'txn-card',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
            paymentMethod: 'credit_card',
        );

        $boletoJob = new ProcessPaymentJob(
            transactionId: 'txn-boleto',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
            paymentMethod: 'boleto',
        );

        $this->assertContains('method:pix', $pixJob->tags());
        $this->assertContains('method:credit_card', $cardJob->tags());
        $this->assertContains('method:boleto', $boletoJob->tags());
    }

    #[Test]
    public function it_accepts_additional_payment_data(): void
    {
        $job = new ProcessPaymentJob(
            transactionId: 'txn-123',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
            paymentMethod: 'credit_card',
            paymentData: [
                'card_token' => 'tok_xxxxx',
                'installments' => 3,
            ],
        );

        $this->assertInstanceOf(ProcessPaymentJob::class, $job);
    }

    #[Test]
    public function different_transaction_ids_produce_different_unique_ids(): void
    {
        $job1 = new ProcessPaymentJob(
            transactionId: 'txn-123',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
        );

        $job2 = new ProcessPaymentJob(
            transactionId: 'txn-456',
            tenantId: 'tenant-123',
            invoiceId: 'inv-123',
            amount: 10000,
        );

        $this->assertNotEquals($job1->uniqueId(), $job2->uniqueId());
    }
}
