<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Chat\Models\ChatInstance;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFile;
use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformReportsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * @group platform
 * @group plan-enforcement
 */
class PlatformPlanEnforcementServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformPlanEnforcementService $service;

    private string $tenantId;

    private PlatformPlan $tenantPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlatformPlanEnforcementService;

        // Create a real tenant (factory always associates a plan)
        $tenant = PlatformTenant::factory()->create();
        $this->tenantId = $tenant->id;
        $this->tenantPlan = PlatformPlan::query()->findOrFail($tenant->plan_id);

        // Ensure 'Inquilino' role exists for guard 'sanctum'
        if (! AuthRole::query()->where('id', AuthRole::INQUILINO_ID)->where('guard_name', 'sanctum')->exists()) {
            AuthRole::create(['id' => AuthRole::INQUILINO_ID, 'name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']);
        }

        Storage::fake('public');
    }

    public function test_get_current_plan_returns_tenant_plan_when_no_invoice(): void
    {
        $plan = $this->service->getCurrentPlan($this->tenantId);

        expect($plan)->not->toBeNull();
        expect($plan->id)->toBe($this->tenantPlan->id);
    }

    public function test_get_current_plan_returns_plan_from_paid_invoice(): void
    {
        $plan = PlatformPlan::factory()->create();

        BillingInvoice::factory()->create([
            'tenant_id' => $this->tenantId,
            'plan_id' => $plan->id,
            'status' => BillingInvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        $result = $this->service->getCurrentPlan($this->tenantId);

        expect($result)->not->toBeNull();
        expect($result->id)->toBe($plan->id);
    }

    public function test_get_current_plan_returns_plan_from_pending_invoice(): void
    {
        $plan = PlatformPlan::factory()->create();

        BillingInvoice::factory()->create([
            'tenant_id' => $this->tenantId,
            'plan_id' => $plan->id,
            'status' => BillingInvoiceStatus::PENDING,
        ]);

        $result = $this->service->getCurrentPlan($this->tenantId);

        expect($result)->not->toBeNull();
        expect($result->id)->toBe($plan->id);
    }

    public function test_get_current_plan_fallback_to_tenant_plan_when_invoice_is_cancelled(): void
    {
        $invoicePlan = PlatformPlan::factory()->create();

        BillingInvoice::factory()->create([
            'tenant_id' => $this->tenantId,
            'plan_id' => $invoicePlan->id,
            'status' => BillingInvoiceStatus::CANCELLED,
        ]);

        $result = $this->service->getCurrentPlan($this->tenantId);

        expect($result)->not->toBeNull();
        expect($result->id)->toBe($this->tenantPlan->id);
    }

    public function test_get_current_plan_returns_most_recent_invoice(): void
    {
        $oldPlan = PlatformPlan::factory()->create(['name' => 'Old Plan']);
        $newPlan = PlatformPlan::factory()->create(['name' => 'New Plan']);

        BillingInvoice::factory()->create([
            'tenant_id' => $this->tenantId,
            'plan_id' => $oldPlan->id,
            'status' => BillingInvoiceStatus::PAID,
            'paid_at' => now()->subDays(10),
        ]);

        BillingInvoice::factory()->create([
            'tenant_id' => $this->tenantId,
            'plan_id' => $newPlan->id,
            'status' => BillingInvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        $result = $this->service->getCurrentPlan($this->tenantId);

        expect($result->id)->toBe($newPlan->id);
    }

    public function test_is_ai_enabled_returns_false_when_no_plan(): void
    {
        $isAiEnabled = $this->service->isAiEnabled((string) Str::orderedUuid());

        expect($isAiEnabled)->toBeFalse();
    }

    public function test_get_current_plan_returns_null_when_tenant_not_found(): void
    {
        $nonExistentTenantId = (string) Str::orderedUuid();

        $currentPlan = $this->service->getCurrentPlan($nonExistentTenantId);

        expect($currentPlan)->toBeNull();
        expect($this->service->isAiEnabled($nonExistentTenantId))->toBeFalse();
    }

    public function test_is_ai_enabled_returns_true_when_plan_allows_ai(): void
    {
        $plan = PlatformPlan::factory()->create(['ai_enabled' => true]);
        $this->createPaidInvoice($plan);

        $isAiEnabled = $this->service->isAiEnabled($this->tenantId);

        expect($isAiEnabled)->toBeTrue();
    }

    public function test_is_ai_enabled_returns_false_when_plan_blocks_ai(): void
    {
        $plan = PlatformPlan::factory()->create(['ai_enabled' => false]);
        $this->createPaidInvoice($plan);

        $isAiEnabled = $this->service->isAiEnabled($this->tenantId);

        expect($isAiEnabled)->toBeFalse();
    }

    public function test_can_create_user_returns_true_when_plan_limit_is_zero(): void
    {
        $plan = PlatformPlan::factory()->create(['limit_users' => 0]);
        PlatformTenant::query()->where('id', $this->tenantId)->update(['plan_id' => $plan->id]);
        $this->tenantPlan = $plan;

        AuthUser::factory()->count(10)->create(['tenant_id' => $this->tenantId]);

        $canCreate = $this->service->canCreateUser($this->tenantId);

        expect($canCreate)->toBeTrue();
    }

    public function test_can_create_user_returns_true_when_under_limit(): void
    {
        $plan = PlatformPlan::factory()->create(['limit_users' => 5]);
        $this->createPaidInvoice($plan);

        AuthUser::factory()->count(3)->create(['tenant_id' => $this->tenantId]);

        $canCreate = $this->service->canCreateUser($this->tenantId);

        expect($canCreate)->toBeTrue();
    }

    public function test_can_create_user_returns_false_when_at_limit(): void
    {
        $plan = PlatformPlan::factory()->create(['limit_users' => 3]);
        $this->createPaidInvoice($plan);

        AuthUser::factory()->count(3)->create(['tenant_id' => $this->tenantId]);

        $canCreate = $this->service->canCreateUser($this->tenantId);

        expect($canCreate)->toBeFalse();
    }

    public function test_can_create_user_returns_true_when_limit_is_zero(): void
    {
        $plan = PlatformPlan::factory()->create(['limit_users' => 0]);
        $this->createPaidInvoice($plan);

        AuthUser::factory()->count(100)->create(['tenant_id' => $this->tenantId]);

        $canCreate = $this->service->canCreateUser($this->tenantId);

        expect($canCreate)->toBeTrue();
    }

    public function test_can_create_instance_returns_true_when_under_limit(): void
    {
        $plan = PlatformPlan::factory()->create(['chat_channels_limit' => 5]);
        $this->createPaidInvoice($plan);

        ChatInstance::factory()->count(3)->create(['tenant_id' => $this->tenantId]);

        $canCreate = $this->service->canCreateInstance($this->tenantId);

        expect($canCreate)->toBeTrue();
    }

    public function test_can_create_instance_returns_false_when_at_limit(): void
    {
        $plan = PlatformPlan::factory()->create(['chat_channels_limit' => 2]);
        $this->createPaidInvoice($plan);

        ChatInstance::factory()->count(2)->create(['tenant_id' => $this->tenantId]);

        $canCreate = $this->service->canCreateInstance($this->tenantId);

        expect($canCreate)->toBeFalse();
    }

    public function test_can_create_negotiation_returns_true_when_mode_is_unlimited(): void
    {
        $plan = PlatformPlan::factory()->create([
            'negotiations_mode' => PlatformNegotiationsMode::UNLIMITED,
            'negotiations_limit' => 10,
        ]);
        $this->createPaidInvoice($plan);

        CRMNegotiation::factory()->count(15)->create(['tenant_id' => $this->tenantId]);

        $canCreate = $this->service->canCreateNegotiation($this->tenantId);

        expect($canCreate)->toBeTrue();
    }

    public function test_can_create_negotiation_returns_false_when_at_limit(): void
    {
        $plan = PlatformPlan::factory()->create([
            'negotiations_mode' => PlatformNegotiationsMode::LIMITED,
            'negotiations_limit' => 5,
        ]);
        $this->createPaidInvoice($plan);

        CRMNegotiation::factory()->count(5)->create(['tenant_id' => $this->tenantId]);

        $canCreate = $this->service->canCreateNegotiation($this->tenantId);

        expect($canCreate)->toBeFalse();
    }

    public function test_can_create_negotiation_returns_true_when_under_limit(): void
    {
        $plan = PlatformPlan::factory()->create([
            'negotiations_mode' => PlatformNegotiationsMode::LIMITED,
            'negotiations_limit' => 10,
        ]);
        $this->createPaidInvoice($plan);

        CRMNegotiation::factory()->count(7)->create(['tenant_id' => $this->tenantId]);

        $canCreate = $this->service->canCreateNegotiation($this->tenantId);

        expect($canCreate)->toBeTrue();
    }

    public function test_can_upload_file_returns_true_when_storage_unlimited(): void
    {
        $plan = PlatformPlan::factory()->create([
            'storage_mode' => PlatformStorageMode::UNLIMITED,
            'storage_limit_bytes' => 1000,
        ]);
        $this->createPaidInvoice($plan);

        CRMNegotiationFile::factory()->create([
            'tenant_id' => $this->tenantId,
            'size' => 5000,
        ]);

        $canUpload = $this->service->canUploadFile($this->tenantId, 10000);

        expect($canUpload)->toBeTrue();
    }

    public function test_effective_storage_limit_caps_unlimited_plan_to_50mb(): void
    {
        $plan = PlatformPlan::factory()->create([
            'storage_mode' => PlatformStorageMode::UNLIMITED,
            'storage_limit_bytes' => null,
        ]);
        $this->createPaidInvoice($plan);

        $effectiveLimit = $this->service->getEffectiveStorageLimitBytes($this->tenantId);

        expect($effectiveLimit)->toBe(50 * 1024 * 1024);
    }

    public function test_can_upload_file_returns_false_when_exceeds_limit(): void
    {
        $plan = PlatformPlan::factory()->create([
            'storage_mode' => PlatformStorageMode::LIMITED,
            'storage_limit_bytes' => 10000,
        ]);
        $this->createPaidInvoice($plan);

        CRMNegotiationFile::factory()->create([
            'tenant_id' => $this->tenantId,
            'size' => 8000,
        ]);

        $canUpload = $this->service->canUploadFile($this->tenantId, 3000);

        expect($canUpload)->toBeFalse();
    }

    public function test_can_upload_file_returns_true_when_within_limit(): void
    {
        $plan = PlatformPlan::factory()->create([
            'storage_mode' => PlatformStorageMode::LIMITED,
            'storage_limit_bytes' => 10000,
        ]);
        $this->createPaidInvoice($plan);

        CRMNegotiationFile::factory()->create([
            'tenant_id' => $this->tenantId,
            'size' => 5000,
        ]);

        $canUpload = $this->service->canUploadFile($this->tenantId, 4000);

        expect($canUpload)->toBeTrue();
    }

    public function test_can_upload_file_considers_chat_storage(): void
    {
        $plan = PlatformPlan::factory()->create([
            'storage_mode' => PlatformStorageMode::LIMITED,
            'storage_limit_bytes' => 10000,
        ]);
        $this->createPaidInvoice($plan);

        Storage::disk('public')->put("chat/{$this->tenantId}/file-a.bin", str_repeat('a', 3000));
        Storage::disk('public')->put("chat/{$this->tenantId}/file-b.bin", str_repeat('b', 2000));

        CRMNegotiationFile::factory()->create([
            'tenant_id' => $this->tenantId,
            'size' => 3000,
        ]);

        $canUpload = $this->service->canUploadFile($this->tenantId, 3000);

        expect($canUpload)->toBeFalse();
    }

    public function test_tenant_isolation_in_user_count(): void
    {
        $plan = PlatformPlan::factory()->create(['limit_users' => 2]);
        $this->createPaidInvoice($plan);

        $otherTenant = PlatformTenant::factory()->create();
        AuthUser::factory()->count(5)->create(['tenant_id' => $otherTenant->id]);
        AuthUser::factory()->count(1)->create(['tenant_id' => $this->tenantId]);

        $canCreate = $this->service->canCreateUser($this->tenantId);

        expect($canCreate)->toBeTrue();
    }

    public function test_tenant_isolation_in_instance_count(): void
    {
        $plan = PlatformPlan::factory()->create(['chat_channels_limit' => 2]);
        $this->createPaidInvoice($plan);

        $otherTenant = PlatformTenant::factory()->create();
        ChatInstance::factory()->count(5)->create(['tenant_id' => $otherTenant->id]);
        ChatInstance::factory()->count(1)->create(['tenant_id' => $this->tenantId]);

        $canCreate = $this->service->canCreateInstance($this->tenantId);

        expect($canCreate)->toBeTrue();
    }

    public function test_tenant_isolation_in_negotiation_count(): void
    {
        $plan = PlatformPlan::factory()->create([
            'negotiations_mode' => PlatformNegotiationsMode::LIMITED,
            'negotiations_limit' => 3,
        ]);
        $this->createPaidInvoice($plan);

        $otherTenant = PlatformTenant::factory()->create();
        CRMNegotiation::factory()->count(10)->create(['tenant_id' => $otherTenant->id]);
        CRMNegotiation::factory()->count(2)->create(['tenant_id' => $this->tenantId]);

        $canCreate = $this->service->canCreateNegotiation($this->tenantId);

        expect($canCreate)->toBeTrue();
    }

    public function test_tenant_isolation_in_storage_calculation(): void
    {
        $plan = PlatformPlan::factory()->create([
            'storage_mode' => PlatformStorageMode::LIMITED,
            'storage_limit_bytes' => 5000,
        ]);
        $this->createPaidInvoice($plan);

        $otherTenant = PlatformTenant::factory()->create();
        CRMNegotiationFile::factory()->create([
            'tenant_id' => $otherTenant->id,
            'size' => 10000,
        ]);

        CRMNegotiationFile::factory()->create([
            'tenant_id' => $this->tenantId,
            'size' => 2000,
        ]);

        $canUpload = $this->service->canUploadFile($this->tenantId, 2000);

        expect($canUpload)->toBeTrue();
    }

    // ─── Reports Mode ─────────────────────────────────────────────────────────

    public function test_get_reports_mode_returns_basic_when_no_plan(): void
    {
        $mode = $this->service->getReportsMode($this->tenantId);

        expect($mode)->toBe(PlatformReportsMode::BASIC);
    }

    public function test_get_reports_mode_returns_plan_mode(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::ADVANCED]);
        $this->createPaidInvoice($plan);

        $mode = $this->service->getReportsMode($this->tenantId);

        expect($mode)->toBe(PlatformReportsMode::ADVANCED);
    }

    public function test_get_reports_mode_returns_full_for_full_plan(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::FULL]);
        $this->createPaidInvoice($plan);

        $mode = $this->service->getReportsMode($this->tenantId);

        expect($mode)->toBe(PlatformReportsMode::FULL);
    }

    // ─── canViewReport ────────────────────────────────────────────────────────

    public function test_can_view_report_basic_allows_only_volume(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::BASIC]);
        $this->createPaidInvoice($plan);

        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.chat.volume'));
        $this->assertFalse($this->service->canViewReport($this->tenantId, 'reports.chat.agent_performance'));
        $this->assertFalse($this->service->canViewReport($this->tenantId, 'reports.crm.funnel'));
        $this->assertFalse($this->service->canViewReport($this->tenantId, 'reports.ai.autopilot_performance'));
    }

    public function test_can_view_report_advanced_allows_expected_reports(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::ADVANCED]);
        $this->createPaidInvoice($plan);

        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.chat.volume'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.chat.agent_performance'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.crm.funnel'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.crm.salesperson_performance'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.crm.loss_reason'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.crm.contact_crm'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.ai.autopilot_performance'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.ai.sentiment'));
        $this->assertFalse($this->service->canViewReport($this->tenantId, 'reports.sla.resolution'));
        $this->assertFalse($this->service->canViewReport($this->tenantId, 'reports.csat_nps'));
        $this->assertFalse($this->service->canViewReport($this->tenantId, 'reports.export'));
    }

    public function test_can_view_report_full_allows_all_reports(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::FULL]);
        $this->createPaidInvoice($plan);

        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.chat.volume'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.chat.agent_performance'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.crm.funnel'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.crm.salesperson_performance'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.crm.loss_reason'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.crm.contact_crm'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.ai.autopilot_performance'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.ai.sentiment'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.sla.resolution'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.csat_nps'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.export'));
    }

    public function test_can_view_report_admin_bypasses_plan_restrictions(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::BASIC]);
        $this->createPaidInvoice($plan);

        $admin = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
        $admin->assignRole(AuthRole::INQUILINO_ID);

        $this->actingAs($admin, 'sanctum');

        // Admin always sees everything
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.chat.volume'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.ai.usage_cost'));
        $this->assertTrue($this->service->canViewReport($this->tenantId, 'reports.billing.revenue'));
    }

    public function test_can_view_report_non_admin_does_not_see_admin_only_reports(): void
    {
        $plan = PlatformPlan::factory()->create(['reports_mode' => PlatformReportsMode::FULL]);
        $this->createPaidInvoice($plan);

        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $this->actingAs($user, 'sanctum');

        $this->assertFalse($this->service->canViewReport($this->tenantId, 'reports.ai.usage_cost'));
        $this->assertFalse($this->service->canViewReport($this->tenantId, 'reports.billing.revenue'));
    }

    // ─── isAdmin ─────────────────────────────────────────────────────────────

    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $admin = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
        $admin->assignRole(AuthRole::INQUILINO_ID);

        $this->assertTrue($this->service->isAdmin($admin));
    }

    public function test_is_admin_returns_false_for_non_admin(): void
    {
        $user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $this->assertFalse($this->service->isAdmin($user));
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
