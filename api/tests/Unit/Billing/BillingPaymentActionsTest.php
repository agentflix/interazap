<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use Domain\Billing\Actions\BillingInvoiceActions;
use Domain\Billing\DTOs\BillingInvoiceDTO;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class BillingPaymentActionsTest extends TestCase
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

    public function test_list_returns_paginated_invoices_for_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        BillingInvoice::factory()->count(3)
            ->state(['tenant_id' => $tenant->id])
            ->sequence(fn (Sequence $sequence): array => ['reference_month' => $this->referenceMonth($sequence->index)])
            ->create();
        BillingInvoice::factory()->count(2)
            ->state(['tenant_id' => $otherTenant->id])
            ->sequence(fn (Sequence $sequence): array => ['reference_month' => $this->referenceMonth($sequence->index)])
            ->create();

        $result = $this->actions->list($tenant->id);

        $this->assertCount(3, $result->items());
        foreach ($result->items() as $invoice) {
            $this->assertSame($tenant->id, $invoice->tenant_id);
        }
    }

    public function test_list_applies_filters_correctly(): void
    {
        $tenant = PlatformTenant::factory()->create();

        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PAID->value,
            'reference_month' => $this->referenceMonth(0),
        ]);
        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PENDING->value,
            'reference_month' => $this->referenceMonth(1),
        ]);
        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::DRAFT->value,
            'reference_month' => $this->referenceMonth(2),
        ]);

        $result = $this->actions->list($tenant->id, ['status' => BillingInvoiceStatus::PAID->value]);

        $this->assertCount(1, $result->items());
        $this->assertSame(BillingInvoiceStatus::PAID, $result->items()[0]->status);
    }

    public function test_find_returns_invoice_with_relations(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $plan = PlatformPlan::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ]);

        $found = $this->actions->find($tenant->id, (string) $invoice->id);

        $this->assertSame($invoice->id, $found->id);
        $this->assertTrue($found->relationLoaded('plan'));
        $this->assertTrue($found->relationLoaded('payments'));
    }

    public function test_find_throws_when_invoice_belongs_to_different_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();
        $invoice = BillingInvoice::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->actions->find($tenant->id, (string) $invoice->id);
    }

    public function test_create_creates_invoice_for_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $dto = BillingInvoiceDTO::fromArray([
            'reference_month' => '2026-02',
            'amount' => 199.99,
            'status' => BillingInvoiceStatus::DRAFT->value,
            'due_date' => '2026-02-15',
        ]);

        $invoice = $this->actions->create($tenant->id, $dto);

        $this->assertSame($tenant->id, $invoice->tenant_id);
        $this->assertSame('2026-02', $invoice->reference_month);
        $this->assertEquals(199.99, $invoice->amount);
        $this->assertSame(BillingInvoiceStatus::DRAFT, $invoice->status);
    }

    public function test_update_allows_editing_draft_invoice(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::DRAFT->value,
            'amount' => 100,
        ]);

        $dto = BillingInvoiceDTO::fromArray([
            'reference_month' => '2026-03',
            'amount' => 250.00,
            'status' => BillingInvoiceStatus::DRAFT->value,
            'due_date' => '2026-03-10',
        ]);

        $updated = $this->actions->update($tenant->id, (string) $invoice->id, $dto);

        $this->assertEquals(250.00, $updated->amount);
        $this->assertSame('2026-03', $updated->reference_month);
    }

    public function test_update_throws_when_invoice_cancelled(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::CANCELLED->value,
        ]);

        $dto = BillingInvoiceDTO::fromArray([
            'reference_month' => '2026-01',
            'amount' => 99.9,
            'status' => BillingInvoiceStatus::CANCELLED->value,
            'due_date' => '2026-01-10',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Não é possível editar uma fatura paga ou cancelada.');

        $this->actions->update($tenant->id, (string) $invoice->id, $dto);
    }

    public function test_delete_cancels_pending_invoice(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $invoice = BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BillingInvoiceStatus::PENDING->value,
        ]);

        $this->actions->delete($tenant->id, (string) $invoice->id);

        $invoice->refresh();
        $this->assertSame(BillingInvoiceStatus::CANCELLED, $invoice->status);
    }

    public function test_list_for_admin_returns_all_tenants(): void
    {
        $tenant1 = PlatformTenant::factory()->create();
        $tenant2 = PlatformTenant::factory()->create();

        BillingInvoice::factory()->count(2)
            ->state(['tenant_id' => $tenant1->id])
            ->sequence(fn (Sequence $sequence): array => ['reference_month' => $this->referenceMonth($sequence->index)])
            ->create();
        BillingInvoice::factory()->count(3)
            ->state(['tenant_id' => $tenant2->id])
            ->sequence(fn (Sequence $sequence): array => ['reference_month' => $this->referenceMonth($sequence->index)])
            ->create();

        $result = $this->actions->listForAdmin();

        $this->assertGreaterThanOrEqual(5, $result->total());
    }

    public function test_list_for_admin_filters_by_tenant_id(): void
    {
        $tenant1 = PlatformTenant::factory()->create();
        $tenant2 = PlatformTenant::factory()->create();

        BillingInvoice::factory()->count(2)
            ->state(['tenant_id' => $tenant1->id])
            ->sequence(fn (Sequence $sequence): array => ['reference_month' => $this->referenceMonth($sequence->index)])
            ->create();
        BillingInvoice::factory()->count(3)
            ->state(['tenant_id' => $tenant2->id])
            ->sequence(fn (Sequence $sequence): array => ['reference_month' => $this->referenceMonth($sequence->index)])
            ->create();

        $result = $this->actions->listForAdmin(['tenant_id' => $tenant1->id]);

        $this->assertCount(2, $result->items());
        foreach ($result->items() as $invoice) {
            $this->assertSame($tenant1->id, $invoice->tenant_id);
        }
    }

    private function referenceMonth(int $offset): string
    {
        return now()->startOfMonth()->addMonths($offset)->format('Y-m');
    }
}
