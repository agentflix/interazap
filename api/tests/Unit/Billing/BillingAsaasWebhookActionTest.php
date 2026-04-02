<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use Domain\Billing\Actions\BillingAsaasWebhookAction;
use Domain\Billing\DTOs\BillingWebhookDTO;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Enums\BillingPaymentStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Models\BillingPayment;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingAsaasWebhookActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_payment_received_marks_invoice_paid_and_creates_payment(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PENDING->value,
        ]);

        $payload = [
            'provider' => 'ASAAS',
            'event_type' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_123',
                'value' => 120.5,
                'billingType' => 'PIX',
                'externalReference' => (string) $invoice->id,
            ],
        ];

        $dto = BillingWebhookDTO::fromArray($payload);

        /** @var BillingAsaasWebhookAction $action */
        $action = app(BillingAsaasWebhookAction::class);

        $result = $action->handle($tenant, $dto, true);

        $invoice->refresh();

        $this->assertTrue($result['invoice_updated']);
        $this->assertSame(BillingInvoiceStatus::PAID, $invoice->status);

        $payment = BillingPayment::query()->where('invoice_id', $invoice->id)->first();

        $this->assertNotNull($payment);
        $this->assertSame(BillingPaymentStatus::CONFIRMED, $payment?->status);
        $this->assertSame('asaas', $payment?->provider);
        $this->assertSame('pay_123', $payment?->provider_payment_id);
    }

    public function test_payment_overdue_marks_invoice_overdue(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PENDING->value,
        ]);

        $payload = [
            'provider' => 'ASAAS',
            'event_type' => 'PAYMENT_OVERDUE',
            'payment' => [
                'id' => 'pay_overdue',
                'billingType' => 'BOLETO',
                'externalReference' => (string) $invoice->id,
            ],
        ];

        $dto = BillingWebhookDTO::fromArray($payload);

        /** @var BillingAsaasWebhookAction $action */
        $action = app(BillingAsaasWebhookAction::class);

        $result = $action->handle($tenant, $dto, true);

        $invoice->refresh();

        $this->assertTrue($result['invoice_updated']);
        $this->assertSame(BillingInvoiceStatus::OVERDUE, $invoice->status);
    }

    public function test_handle_throws_validation_exception_when_signature_is_invalid(): void
    {
        config()->set('services.asaas.webhook_token', 'valid-token');

        $tenant = PlatformTenant::factory()->create();

        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PENDING->value,
        ]);

        $dto = BillingWebhookDTO::fromArray([
            'provider' => 'ASAAS',
            'event_type' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_invalid_signature',
                'value' => 50.00,
                'billingType' => 'PIX',
                'externalReference' => (string) $invoice->id,
            ],
        ]);

        $request = Request::create('/webhook/billing', 'POST');
        $request->headers->set('asaas-access-token', 'invalid-token');

        /** @var BillingAsaasWebhookAction $action */
        $action = app(BillingAsaasWebhookAction::class);

        $this->expectException(ValidationException::class);

        $action->handle($tenant, $dto, true, $request);
    }
}
