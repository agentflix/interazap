<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlatformPlanEnforcementServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_tenant_plan_when_no_invoice_exists(): void
    {
        $plan = PlatformPlan::factory()->create(['ai_enabled' => true]);
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);

        $service = new PlatformPlanEnforcementService;
        $currentPlan = $service->getCurrentPlan($tenant->id);

        $this->assertNotNull($currentPlan);
        $this->assertSame($plan->id, $currentPlan->id);
        $this->assertTrue($service->isAiEnabled($tenant->id));
    }

    public function test_returns_invoice_plan_over_tenant_plan(): void
    {
        $tenantPlan = PlatformPlan::factory()->create(['ai_enabled' => false]);
        $invoicePlan = PlatformPlan::factory()->create(['ai_enabled' => true]);

        $tenant = PlatformTenant::factory()->create(['plan_id' => $tenantPlan->id]);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $invoicePlan->id,
            'status' => BillingInvoiceStatus::PAID->value,
            'due_date' => now()->toDateString(),
            'paid_at' => now(),
        ]);

        $service = new PlatformPlanEnforcementService;
        $currentPlan = $service->getCurrentPlan($tenant->id);

        $this->assertNotNull($currentPlan);
        $this->assertSame($invoicePlan->id, $currentPlan->id);
        $this->assertTrue($service->isAiEnabled($tenant->id));
    }

    public function test_returns_null_when_no_invoice_and_tenant_not_found(): void
    {
        $nonExistentTenantId = (string) \Illuminate\Support\Str::uuid();

        $service = new PlatformPlanEnforcementService;
        $currentPlan = $service->getCurrentPlan($nonExistentTenantId);

        $this->assertNull($currentPlan);
        $this->assertFalse($service->isAiEnabled($nonExistentTenantId));
    }

    public function test_fallback_to_tenant_plan_when_invoice_is_cancelled(): void
    {
        $tenantPlan = PlatformPlan::factory()->create(['ai_enabled' => true]);
        $invoicePlan = PlatformPlan::factory()->create(['ai_enabled' => false]);

        $tenant = PlatformTenant::factory()->create(['plan_id' => $tenantPlan->id]);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $invoicePlan->id,
            'status' => BillingInvoiceStatus::CANCELLED->value,
            'due_date' => now()->toDateString(),
            'paid_at' => null,
        ]);

        $service = new PlatformPlanEnforcementService;
        $currentPlan = $service->getCurrentPlan($tenant->id);

        $this->assertNotNull($currentPlan);
        $this->assertSame($tenantPlan->id, $currentPlan->id);
        $this->assertTrue($service->isAiEnabled($tenant->id));
    }
}
