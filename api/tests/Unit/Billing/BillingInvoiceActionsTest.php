<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use Domain\Billing\Actions\BillingInvoiceActions;
use Domain\Billing\DTOs\BillingInvoiceDTO;
use Domain\Billing\DTOs\BillingInvoicePaymentDTO;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class BillingInvoiceActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private BillingInvoiceActions $actions;

    private $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = Mockery::mock(BillingGatewayService::class);
        $this->actions = new BillingInvoiceActions($this->gateway);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_update_throws_when_invoice_paid_or_cancelled(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PAID->value,
        ]);

        $dto = BillingInvoiceDTO::fromArray([
            'reference_month' => '2026-01',
            'amount' => 99.9,
            'status' => BillingInvoiceStatus::PAID->value,
            'due_date' => '2026-01-10',
        ]);

        $this->expectException(\DomainException::class);
        $this->actions->update($tenant->id, (string) $invoice->id, $dto);
    }

    public function test_delete_marks_invoice_cancelled_or_throws_when_paid(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $paid = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PAID->value,
        ]);

        $this->expectException(\DomainException::class);
        $this->actions->delete($tenant->id, (string) $paid->id);

        $pending = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PENDING->value,
        ]);

        $this->actions->delete($tenant->id, (string) $pending->id);

        $pending->refresh();
        $this->assertSame(BillingInvoiceStatus::CANCELLED, $pending->status);
    }

    public function test_pay_pix_updates_invoice_and_pix_data(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::DRAFT->value,
            'reference_month' => '2026-01',
            'amount' => 120.5,
            'due_date' => '2026-01-25',
        ]);

        $this->gateway->shouldReceive('ensureCustomer')
            ->once()
            ->with(Mockery::type(PlatformTenant::class))
            ->andReturn('cust_1');

        $this->gateway->shouldReceive('createPayment')
            ->once()
            ->andReturn([
                'id' => 'pay_1',
                'invoiceUrl' => 'http://asaas.test/pay/1',
                'status' => 'PENDING',
            ]);

        $this->gateway->shouldReceive('getPixQRCode')
            ->once()
            ->with('pay_1')
            ->andReturn([
                'payload' => 'pix-payload',
                'encodedImage' => 'pix-image',
            ]);

        $paymentDto = new BillingInvoicePaymentDTO('pix', null, null);

        $result = $this->actions->pay($tenant->id, (string) $invoice->id, $paymentDto);

        $updatedInvoice = $result['invoice'];

        $this->assertSame(BillingInvoiceStatus::PENDING, $updatedInvoice->status);
        $this->assertSame('pix', $updatedInvoice->payment_method);
        $this->assertSame('http://asaas.test/pay/1', $updatedInvoice->payment_url);
        $this->assertSame('pay_1', $updatedInvoice->asaas_payment_id);
        $this->assertSame('pix-payload', $updatedInvoice->pix_payload);
        $this->assertSame('pix-image', $updatedInvoice->pix_qr_code_base64);
    }

    public function test_get_pix_returns_existing_payload(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'payment_method' => 'pix',
            'pix_payload' => 'payload-1',
            'pix_qr_code_base64' => 'img-1',
        ]);

        $this->gateway->shouldNotReceive('getPixQRCode');

        $pix = $this->actions->getPix($tenant->id, (string) $invoice->id);

        $this->assertSame('payload-1', $pix['payload']);
        $this->assertSame('img-1', $pix['qr_code']);
    }

    public function test_get_pix_fetches_when_missing_payload(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'payment_method' => 'pix',
            'asaas_payment_id' => 'pay_2',
            'pix_payload' => null,
            'pix_qr_code_base64' => null,
        ]);

        $this->gateway->shouldReceive('getPixQRCode')
            ->once()
            ->with('pay_2')
            ->andReturn([
                'payload' => 'payload-2',
                'encodedImage' => 'img-2',
            ]);

        $pix = $this->actions->getPix($tenant->id, (string) $invoice->id);

        $this->assertSame('payload-2', $pix['payload']);
        $this->assertSame('img-2', $pix['qr_code']);

        $invoice->refresh();
        $this->assertSame('payload-2', $invoice->pix_payload);
        $this->assertSame('img-2', $invoice->pix_qr_code_base64);
    }

    public function test_pay_throws_when_cannot_be_paid(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PAID->value,
        ]);

        $paymentDto = new BillingInvoicePaymentDTO('pix', null, null);

        $this->expectException(\DomainException::class);
        $this->actions->pay($tenant->id, (string) $invoice->id, $paymentDto);
    }
}
