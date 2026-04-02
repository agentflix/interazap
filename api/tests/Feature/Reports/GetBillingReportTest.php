<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class GetBillingReportTest extends \Tests\Feature\Reports\ReportsTestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    private string $tenantId;

    private int $referenceMonthOffset = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = AuthUser::factory()->create();
        $this->tenantId = (string) $this->user->tenant_id;
        $this->user->givePermissionTo('reports.billing.view');
        Sanctum::actingAs($this->user, abilities: ['*']);
    }

    public function test_returns_billing_summary(): void
    {
        $this->createInvoice(status: 'paid', amount: 100.00, paidAt: now());
        $this->createInvoice(status: 'paid', amount: 200.00, paidAt: now());
        $this->createInvoice(status: 'overdue', amount: 50.00);

        $response = $this->getJson('/api/reports/billing?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $summary = $response->json('data.data.summary');
        $this->assertSame(350.0, (float) $summary['total_invoiced']);
        $this->assertSame(300.0, (float) $summary['total_paid']);
        $this->assertSame(50.0, (float) $summary['total_overdue']);
    }

    public function test_returns_by_payment_method(): void
    {
        $this->createInvoice(amount: 100.00, paymentMethod: 'pix');
        $this->createInvoice(amount: 200.00, paymentMethod: 'credit_card');

        $response = $this->getJson('/api/reports/billing?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $byMethod = $response->json('data.data.by_payment_method');
        $this->assertCount(2, $byMethod);
    }

    public function test_returns_monthly_revenue(): void
    {
        $this->createInvoice(amount: 100.00, referenceMonth: '2025-01');
        $this->createInvoice(amount: 200.00, referenceMonth: '2025-02');

        $response = $this->getJson('/api/reports/billing?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $monthly = $response->json('data.data.monthly_revenue');
        $this->assertCount(2, $monthly);
    }

    public function test_tenant_isolation(): void
    {
        $otherUser = AuthUser::factory()->create();
        DB::table('billing_invoices')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $otherUser->tenant_id,
            'reference_month' => '2025-01',
            'amount' => 500.00,
            'status' => 'paid',
            'due_date' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/reports/billing?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertSame(0.0, (float) $response->json('data.data.summary.total_invoiced'));
    }

    public function test_returns_overdue_rate(): void
    {
        $this->createInvoice(status: 'paid', amount: 100.00, paidAt: now());
        $this->createInvoice(status: 'overdue', amount: 50.00);

        $response = $this->getJson('/api/reports/billing?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertSame(50.0, (float) $response->json('data.data.summary.overdue_rate'));
    }

    public function test_returns_empty_when_no_data(): void
    {
        $response = $this->getJson('/api/reports/billing?start_date=2020-01-01&end_date=2030-12-31');

        $response->assertStatus(200);
        $this->assertSame(0.0, (float) $response->json('data.data.summary.total_invoiced'));
        $this->assertEmpty($response->json('data.data.by_payment_method'));
    }

    /**
     * Helper para criar uma fatura.
     */
    private function createInvoice(
        string $status = 'pending',
        float $amount = 100.00,
        ?string $paymentMethod = null,
        ?string $referenceMonth = null,
        ?\DateTimeInterface $paidAt = null,
    ): void {
        DB::table('billing_invoices')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenantId,
            'reference_month' => $referenceMonth ?? now()->addMonths($this->referenceMonthOffset++)->format('Y-m'),
            'amount' => $amount,
            'status' => $status,
            'due_date' => now()->addDays(30),
            'paid_at' => $paidAt,
            'payment_method' => $paymentMethod,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
