<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use Domain\Billing\Actions\ProcessPaymentAction;
use Domain\Billing\DTOs\BillingPaymentDTO;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Models\BillingPayment;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessPaymentActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ProcessPaymentAction $action;

    private PlatformTenant $tenant;

    private BillingInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ProcessPaymentAction;
        Cache::flush();

        $this->tenant = PlatformTenant::factory()->create();
        $this->invoice = BillingInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => 100.00,
            'status' => BillingInvoiceStatus::PENDING,
        ]);
    }

    #[Test]
    public function it_processes_payment_successfully(): void
    {
        $dto = new BillingPaymentDTO(
            invoiceId: $this->invoice->id,
            amount: 100.00,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: 'pay_'.Str::random(10),
        );

        $result = $this->action->handle($this->tenant, $dto);

        $this->assertTrue($result['created']);
        $this->assertInstanceOf(BillingPayment::class, $result['payment']);
        $this->assertNull($result['reason']);

        $this->assertDatabaseHas('billing_payments', [
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $this->invoice->id,
            'amount' => 100.00,
        ]);
    }

    #[Test]
    public function it_prevents_duplicate_payment(): void
    {
        $providerPaymentId = 'pay_'.Str::random(10);

        $dto = new BillingPaymentDTO(
            invoiceId: $this->invoice->id,
            amount: 100.00,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: $providerPaymentId,
        );

        // Primeiro pagamento
        $result1 = $this->action->handle($this->tenant, $dto);
        $this->assertTrue($result1['created']);

        // Tentativa de duplicar (mesmo invoice, mesmo amount, mesmo dia)
        $dto2 = new BillingPaymentDTO(
            invoiceId: $this->invoice->id,
            amount: 100.00,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: 'pay_'.Str::random(10), // ID diferente do provider
        );

        $result2 = $this->action->handle($this->tenant, $dto2);

        $this->assertFalse($result2['created']);
        $this->assertSame('duplicate_payment', $result2['reason']);
        $this->assertSame($result1['payment']->id, $result2['payment']->id);
    }

    #[Test]
    public function it_allows_different_amounts(): void
    {
        $dto1 = new BillingPaymentDTO(
            invoiceId: $this->invoice->id,
            amount: 50.00,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: 'pay_'.Str::random(10),
        );

        $dto2 = new BillingPaymentDTO(
            invoiceId: $this->invoice->id,
            amount: 50.01,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: 'pay_'.Str::random(10),
        );

        $result1 = $this->action->handle($this->tenant, $dto1);
        $result2 = $this->action->handle($this->tenant, $dto2);

        $this->assertTrue($result1['created']);
        $this->assertTrue($result2['created']);
    }

    #[Test]
    public function it_returns_error_for_missing_invoice(): void
    {
        $dto = new BillingPaymentDTO(
            invoiceId: (string) Str::orderedUuid(), // UUID válido mas inexistente
            amount: 100.00,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: 'pay_'.Str::random(10),
        );

        $result = $this->action->handle($this->tenant, $dto);

        $this->assertFalse($result['created']);
        $this->assertNull($result['payment']);
        $this->assertSame('invoice_not_found', $result['reason']);
    }

    #[Test]
    public function it_updates_invoice_status_when_fully_paid(): void
    {
        $dto = new BillingPaymentDTO(
            invoiceId: $this->invoice->id,
            amount: 100.00,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: 'pay_'.Str::random(10),
        );

        $this->action->handle($this->tenant, $dto);

        $this->invoice->refresh();
        $this->assertSame(BillingInvoiceStatus::PAID, $this->invoice->status);
        $this->assertNotNull($this->invoice->paid_at);
    }

    #[Test]
    public function it_can_skip_idempotency_check(): void
    {
        $dto1 = new BillingPaymentDTO(
            invoiceId: $this->invoice->id,
            amount: 100.00,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: 'pay_'.Str::random(10),
        );

        $dto2 = new BillingPaymentDTO(
            invoiceId: $this->invoice->id,
            amount: 100.00,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: 'pay_'.Str::random(10),
        );

        $result1 = $this->action->handle($this->tenant, $dto1, skipIdempotency: true);
        $result2 = $this->action->handle($this->tenant, $dto2, skipIdempotency: true);

        $this->assertTrue($result1['created']);
        $this->assertTrue($result2['created']);
    }

    #[Test]
    public function it_can_clear_idempotency_key(): void
    {
        $dto = new BillingPaymentDTO(
            invoiceId: $this->invoice->id,
            amount: 100.00,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: 'pay_'.Str::random(10),
        );

        $result1 = $this->action->handle($this->tenant, $dto);
        $this->assertTrue($result1['created']);

        // Limpar chave de idempotência
        $this->action->clearIdempotencyKey($this->tenant, $dto);

        // Criar novo DTO com provider_payment_id diferente
        $dto2 = new BillingPaymentDTO(
            invoiceId: $this->invoice->id,
            amount: 100.00,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: 'pay_'.Str::random(10),
        );

        $result2 = $this->action->handle($this->tenant, $dto2);
        $this->assertTrue($result2['created']);
    }

    #[Test]
    public function it_isolates_by_tenant(): void
    {
        $tenant2 = PlatformTenant::factory()->create();
        $invoice2 = BillingInvoice::factory()->create([
            'tenant_id' => $tenant2->id,
            'amount' => 100.00,
        ]);

        $dto1 = new BillingPaymentDTO(
            invoiceId: $this->invoice->id,
            amount: 100.00,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: 'pay_'.Str::random(10),
        );

        $dto2 = new BillingPaymentDTO(
            invoiceId: $invoice2->id,
            amount: 100.00,
            paymentMethod: 'pix',
            provider: 'asaas',
            providerPaymentId: 'pay_'.Str::random(10),
        );

        $result1 = $this->action->handle($this->tenant, $dto1);
        $result2 = $this->action->handle($tenant2, $dto2);

        $this->assertTrue($result1['created']);
        $this->assertTrue($result2['created']);
    }
}
