<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Policies;

use Domain\Ai\Models\AiPromptMaster;
use Domain\Ai\Models\AiPromptPlan;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Ai\Policies\AiPromptMasterPolicy;
use Domain\Ai\Policies\AiPromptPlanPolicy;
use Domain\Ai\Policies\AiPromptSegmentPolicy;
use Domain\Ai\Policies\AiPromptTenantPolicy;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiPromptPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $tenantAdmin;

    private AuthUser $tenantUser;

    private PlatformTenant $tenant;

    private PlatformTenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = PlatformTenant::factory()->create();
        $this->otherTenant = PlatformTenant::factory()->create();

        // Create Inquilino role
        if (! \Domain\Auth\Models\AuthRole::query()->where('id', AuthRole::INQUILINO_ID)->where('guard_name', 'sanctum')->exists()) {
            \Domain\Auth\Models\AuthRole::query()->firstOrCreate(['id' => AuthRole::INQUILINO_ID], ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']);
        }

        // Tenant admin user (has Inquilino role but is associated with a tenant)
        $this->tenantAdmin = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->tenantAdmin->assignRole(AuthRole::INQUILINO_ID);

        // Regular tenant user (no admin role)
        $this->tenantUser = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // --- Master Policy ---
    // Note: Master policies require tenant_id === null for admin access.
    // Since our schema requires tenant_id (FK constraint), we test that tenant users are denied.

    public function test_master_policy_denies_tenant_users(): void
    {
        $policy = new AiPromptMasterPolicy;
        $master = AiPromptMaster::factory()->create();

        // Tenant Admin (has role but also has tenant_id, so should be denied)
        $this->assertFalse($policy->viewAny($this->tenantAdmin));
        $this->assertFalse($policy->view($this->tenantAdmin, $master));
        $this->assertFalse($policy->create($this->tenantAdmin));
        $this->assertFalse($policy->update($this->tenantAdmin, $master));
        $this->assertFalse($policy->delete($this->tenantAdmin, $master));

        // Regular Tenant User
        $this->assertFalse($policy->viewAny($this->tenantUser));
        $this->assertFalse($policy->view($this->tenantUser, $master));
        $this->assertFalse($policy->create($this->tenantUser));
        $this->assertFalse($policy->update($this->tenantUser, $master));
        $this->assertFalse($policy->delete($this->tenantUser, $master));
    }

    // --- Plan Policy ---

    public function test_plan_policy_denies_tenant_users(): void
    {
        $policy = new AiPromptPlanPolicy;
        $planPrompt = AiPromptPlan::factory()->create();

        // Tenant Admin (has role but also has tenant_id)
        $this->assertFalse($policy->viewAny($this->tenantAdmin));
        $this->assertFalse($policy->view($this->tenantAdmin, $planPrompt));
        $this->assertFalse($policy->create($this->tenantAdmin));
        $this->assertFalse($policy->update($this->tenantAdmin, $planPrompt));

        // Regular Tenant User
        $this->assertFalse($policy->viewAny($this->tenantUser));
        $this->assertFalse($policy->view($this->tenantUser, $planPrompt));
        $this->assertFalse($policy->create($this->tenantUser));
        $this->assertFalse($policy->update($this->tenantUser, $planPrompt));
    }

    // --- Segment Policy ---

    public function test_segment_policy_protects_general_segment(): void
    {
        $policy = new AiPromptSegmentPolicy;
        $generalSegment = AiPromptSegment::factory()->create([
            'name' => 'GENERAL',
            'code' => AiPromptSegment::CODE_GENERAL,
        ]);

        // Even if someone could delete, GENERAL segment is protected
        $this->assertFalse($policy->delete($this->tenantAdmin, $generalSegment));
        $this->assertFalse($policy->delete($this->tenantUser, $generalSegment));
    }

    public function test_segment_policy_denies_tenant_users(): void
    {
        $policy = new AiPromptSegmentPolicy;
        $segment = AiPromptSegment::factory()->create(['name' => 'Custom']);

        // Tenant Admin (has role but also has tenant_id)
        $this->assertFalse($policy->viewAny($this->tenantAdmin));
        $this->assertFalse($policy->view($this->tenantAdmin, $segment));
        $this->assertFalse($policy->create($this->tenantAdmin));
        $this->assertFalse($policy->update($this->tenantAdmin, $segment));

        // Regular Tenant User
        $this->assertFalse($policy->viewAny($this->tenantUser));
        $this->assertFalse($policy->view($this->tenantUser, $segment));
    }

    // --- Tenant Policy ---
    // This policy allows tenant users to manage their own prompts

    public function test_tenant_policy_allows_tenant_to_view_own_prompts(): void
    {
        $policy = new AiPromptTenantPolicy;
        $myPrompt = AiPromptTenant::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherPrompt = AiPromptTenant::factory()->create(['tenant_id' => $this->otherTenant->id]);

        // Tenant User can view any (list) and view own
        $this->assertTrue($policy->viewAny($this->tenantUser));
        $this->assertTrue($policy->view($this->tenantUser, $myPrompt));
        $this->assertFalse($policy->view($this->tenantUser, $otherPrompt));
    }

    public function test_tenant_policy_allows_tenant_to_update_own_prompts(): void
    {
        $policy = new AiPromptTenantPolicy;
        $myPrompt = AiPromptTenant::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherPrompt = AiPromptTenant::factory()->create(['tenant_id' => $this->otherTenant->id]);

        // Tenant User can update own but not others
        $this->assertTrue($policy->update($this->tenantUser, $myPrompt));
        $this->assertFalse($policy->update($this->tenantUser, $otherPrompt));
    }

    public function test_tenant_policy_denies_quarantine_management_to_tenants(): void
    {
        $policy = new AiPromptTenantPolicy;
        $myPrompt = AiPromptTenant::factory()->create(['tenant_id' => $this->tenant->id]);

        // Tenant users cannot manage quarantine (only super-admin can)
        $this->assertFalse($policy->manageQuarantine($this->tenantUser, $myPrompt));
        $this->assertFalse($policy->manageQuarantine($this->tenantAdmin, $myPrompt));
    }
}
