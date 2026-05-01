<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Billing\Console\Commands\BillingWebhookConsumer;
use Domain\Billing\DTOs\BillingWebhookDTO;
use Domain\Billing\Enums\BillingEventType;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Enums\BillingPaymentStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Models\BillingPayment;
use Domain\Billing\Models\BillingWebhookEvent;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Events\RealtimeBroadcastEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class StreamsBillingCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates a mock Redis connection for the 'gateway' connection.
     *
     * @param  array<string, array<string, array<string, string>>>|null  $xreadgroupReturn
     */
    private function mockGatewayConnection(?array $xreadgroupReturn = null): \Mockery\MockInterface
    {
        $connectionMock = Mockery::mock();
        $connectionMock->shouldReceive('xgroup')->andReturnTrue();
        $connectionMock->shouldReceive('xreadgroup')->once()->andReturn($xreadgroupReturn);
        $connectionMock->shouldReceive('xack')->zeroOrMoreTimes();

        Redis::shouldReceive('connection')->with('gateway')->andReturn($connectionMock);

        return $connectionMock;
    }

    public function test_consumes_billing_stream_persists_event_and_publishes(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $payload = [
            'tenant_id' => $tenant->id,
            'provider' => 'ASAAS',
            'event_type' => BillingEventType::PAYMENT_RECEIVED->value,
            'provider_event_id' => 'pay_123',
        ];

        $streamName = (string) config('billing.streams.payment_received.name', 'billing.payment_received');

        $this->mockGatewayConnection([
            $streamName => [
                '1-0' => [
                    'tenant_id' => $payload['tenant_id'],
                    'provider' => $payload['provider'],
                    'event_type' => $payload['event_type'],
                    'provider_event_id' => $payload['provider_event_id'],
                    'idempotency_key' => 'bill-1',
                ],
            ],
        ]);

        Event::fake([RealtimeBroadcastEvent::class]);

        Artisan::call(BillingWebhookConsumer::class, ['--once' => true]);

        Event::assertDispatched(RealtimeBroadcastEvent::class);

        $this->assertDatabaseHas('shared_webhook_events', [
            'tenant_id' => $payload['tenant_id'],
            'domain' => 'billing',
            'provider_event_id' => $payload['provider_event_id'],
        ]);
    }

    public function test_consumer_marks_invoice_paid_and_creates_payment_record(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PENDING,
            'amount' => 120.50,
        ]);

        $providerEventId = 'pay_flow_123';
        $payload = [
            'payment' => [
                'id' => $providerEventId,
                'value' => 120.50,
                'billingType' => 'PIX',
                'externalReference' => (string) $invoice->id,
            ],
        ];

        $streamName = (string) config('billing.streams.payment_received.name', 'billing.payment_received');

        $this->mockGatewayConnection([
            $streamName => [
                '1-0' => [
                    'tenant_id' => $tenant->id,
                    'provider' => 'ASAAS',
                    'event_type' => BillingEventType::PAYMENT_RECEIVED->value,
                    'provider_event_id' => $providerEventId,
                    'idempotency_key' => hash('sha256', 'asaas|PAYMENT_RECEIVED|'.$providerEventId),
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                ],
            ],
        ]);

        Artisan::call(BillingWebhookConsumer::class, ['--once' => true]);

        $invoice->refresh();

        $this->assertSame(BillingInvoiceStatus::PAID, $invoice->status);

        $payment = BillingPayment::query()->where('invoice_id', $invoice->id)->first();

        $this->assertNotNull($payment);
        $this->assertSame(BillingPaymentStatus::CONFIRMED, $payment?->status);
        $this->assertSame('asaas', $payment?->provider);
        $this->assertSame($providerEventId, $payment?->provider_payment_id);
    }

    public function test_skips_duplicate_billing_event(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $payload = [
            'tenant_id' => $tenant->id,
            'provider' => 'ASAAS',
            'event_type' => BillingEventType::PAYMENT_RECEIVED->value,
            'provider_event_id' => 'pay_dup',
        ];

        $dto = BillingWebhookDTO::fromArray($payload);

        BillingWebhookEvent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'provider' => 'ASAAS',
            'instance_webhook_token' => (string) config('billing.streams.payment_received.instance_webhook_token', 'billing-stream'),
            'provider_event_id' => $payload['provider_event_id'],
            'idempotency_key' => $dto->idempotencyKey,
            'event_type' => $payload['event_type'],
            'payload' => $payload,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'payload_json' => $payload,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        $streamName = (string) config('billing.streams.payment_received.name', 'billing.payment_received');

        $this->mockGatewayConnection([
            $streamName => [
                '1-0' => [
                    'tenant_id' => $payload['tenant_id'],
                    'provider' => $payload['provider'],
                    'event_type' => $payload['event_type'],
                    'provider_event_id' => $payload['provider_event_id'],
                    'idempotency_key' => $dto->idempotencyKey,
                ],
            ],
        ]);

        Event::fake([RealtimeBroadcastEvent::class]);

        Artisan::call(BillingWebhookConsumer::class, ['--once' => true]);

        Event::assertNotDispatched(RealtimeBroadcastEvent::class);
        $this->assertSame(1, BillingWebhookEvent::query()->count());
    }
}
