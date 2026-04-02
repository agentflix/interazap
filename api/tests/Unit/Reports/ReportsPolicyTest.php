<?php

declare(strict_types=1);

namespace Tests\Unit\Reports;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Enums\PlatformReportsMode;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Domain\Reports\Policies\ReportsPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group reports
 * @group policy
 */
class ReportsPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ReportsPolicy $policy;

    private PlatformPlanEnforcementService $planService;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planService = new PlatformPlanEnforcementService;
        $this->policy = new ReportsPolicy($this->planService);

        $tenant = PlatformTenant::factory()->create();
        $this->tenantId = $tenant->id;

        if (! AuthRole::query()->where('name', 'admin')->where('guard_name', 'sanctum')->exists()) {
            AuthRole::create(['name' => 'admin', 'guard_name' => 'sanctum']);
        }
    }

    // ─── viewCrm ─────────────────────────────────────────────────────────────

    public function test_view_crm_returns_false_for_basic_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::BASIC]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $this->assertFalse($this->policy->viewCrm($user));
    }

    public function test_view_crm_returns_true_for_advanced_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::ADVANCED]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $this->assertTrue($this->policy->viewCrm($user));
    }

    public function test_view_crm_returns_true_for_full_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::FULL]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $this->assertTrue($this->policy->viewCrm($user));
    }

    public function test_view_crm_returns_true_for_admin_regardless_of_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::BASIC]);
        $this->createPaidInvoice($plan);

        $admin = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        $this->assertTrue($this->policy->viewCrm($admin));
    }

    // ─── viewChat ─────────────────────────────────────────────────────────────

    public function test_view_chat_returns_true_for_basic_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::BASIC]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        // reports.chat.volume is available in BASIC mode
        $this->assertTrue($this->policy->viewChat($user));
    }

    public function test_view_chat_returns_true_for_advanced_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::ADVANCED]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $this->assertTrue($this->policy->viewChat($user));
    }

    public function test_view_chat_returns_true_for_admin_regardless_of_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::BASIC]);
        $this->createPaidInvoice($plan);

        $admin = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        $this->assertTrue($this->policy->viewChat($admin));
    }

    // ─── viewAi ───────────────────────────────────────────────────────────────

    public function test_view_ai_returns_false_for_basic_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::BASIC]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        // reports.ai.autopilot_performance is not in BASIC mode
        $this->assertFalse($this->policy->viewAi($user));
    }

    public function test_view_ai_returns_true_for_advanced_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::ADVANCED]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $this->assertTrue($this->policy->viewAi($user));
    }

    public function test_view_ai_returns_true_for_full_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::FULL]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $this->assertTrue($this->policy->viewAi($user));
    }

    public function test_view_ai_returns_true_for_admin_regardless_of_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::BASIC]);
        $this->createPaidInvoice($plan);

        $admin = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        $this->assertTrue($this->policy->viewAi($admin));
    }

    // ─── viewBilling ──────────────────────────────────────────────────────────

    public function test_view_billing_returns_false_for_non_admin(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::FULL]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        // reports.billing.revenue is ADMIN_ONLY - non-admin gets false
        $this->assertFalse($this->policy->viewBilling($user));
    }

    public function test_view_billing_returns_true_for_admin(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::FULL]);
        $this->createPaidInvoice($plan);

        $admin = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        $this->assertTrue($this->policy->viewBilling($admin));
    }

    public function test_view_billing_returns_true_for_admin_even_on_basic_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::BASIC]);
        $this->createPaidInvoice($plan);

        $admin = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        // Admin override in canViewReport
        $this->assertTrue($this->policy->viewBilling($admin));
    }

    // ─── viewAdmin ─────────────────────────────────────────────────────────────

    public function test_view_admin_returns_false_for_non_admin(): void
    {
        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $this->assertFalse($this->policy->viewAdmin($user));
    }

    public function test_view_admin_returns_true_for_admin(): void
    {
        $admin = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        $this->assertTrue($this->policy->viewAdmin($admin));
    }

    // ─── export ───────────────────────────────────────────────────────────────

    public function test_export_returns_false_for_basic_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::BASIC]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        // reports.export is only in FULL mode
        $this->assertFalse($this->policy->export($user));
    }

    public function test_export_returns_false_for_advanced_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::ADVANCED]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $this->assertFalse($this->policy->export($user));
    }

    public function test_export_returns_true_for_full_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::FULL]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $this->assertTrue($this->policy->export($user));
    }

    public function test_export_returns_true_for_admin_regardless_of_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::BASIC]);
        $this->createPaidInvoice($plan);

        $admin = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        $this->assertTrue($this->policy->export($admin));
    }

    // ─── tenant isolation ─────────────────────────────────────────────────────

    public function test_policy_respects_tenant_isolation(): void
    {
        $otherTenant = PlatformTenant::factory()->create();
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::ADVANCED]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $otherTenant->id]);

        // reports.crm.funnel should not be visible for this tenant's plan
        $this->assertFalse($this->policy->viewCrm($user));
    }

    private function createPaidInvoice(PlatformPlan $plan): BillingInvoice
    {
        return BillingInvoice::factory()->create([
            'tenant_id' => $this->tenantId,
            'plan_id' => $plan->id,
            'status' => BillingInvoiceStatus::PAID,
            'paid_at' => now(),
        ]);
    }
}
