<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Billing\Jobs;

use Domain\Billing\Jobs\CloseExpiredCyclesJob;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CloseExpiredCyclesJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function it_creates_overage_invoice_for_expired_cycle(): void
    {
        $plan = PlatformPlan::factory()->create([
            'message_limit_monthly' => 10,
            'overage_price_per_message' => 0.05,
        ]);
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);

        \Domain\Billing\Models\TenantMessageUsage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'cycle_start' => '2026-04-15',
            'cycle_end' => '2026-05-14',
            'message_count' => 12,
            'overage_count' => 2,
        ]);

        (new CloseExpiredCyclesJob)->handle();

        $this->assertDatabaseHas('billing_invoices', [
            'tenant_id' => $tenant->id,
            'reference_month' => '2026-05',
        ]);

        $invoice = \Domain\Billing\Models\BillingInvoice::query()->where('tenant_id', $tenant->id)->first();
        $this->assertSame(0.10, (float) $invoice->amount); // 2 * 0.05
    }

    #[Test]
    public function it_does_not_create_duplicate_invoice(): void
    {
        $plan = PlatformPlan::factory()->create(['overage_price_per_message' => 0.05]);
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);

        \Domain\Billing\Models\TenantMessageUsage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'cycle_start' => '2026-04-15',
            'cycle_end' => '2026-05-14',
            'message_count' => 12,
            'overage_count' => 2,
        ]);

        (new CloseExpiredCyclesJob)->handle();
        (new CloseExpiredCyclesJob)->handle(); // segunda vez

        $this->assertSame(1, \Domain\Billing\Models\BillingInvoice::query()->where('tenant_id', $tenant->id)->count());
    }
}
