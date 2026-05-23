<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Domain\Shared\Models\SharedMedia;
use Domain\Shared\Policies\SharedMediaPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class SharedMediaPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_allows_super_admin(): void
    {
        $policy = new SharedMediaPolicy(new PlatformPlanEnforcementService);
        $user = Mockery::mock(AuthUser::class)->makePartial();
        $user->tenant_id = '';
        $user->shouldReceive('isSuperAdmin')->andReturn(true);

        $this->assertTrue($policy->create($user));
    }

    public function test_create_denies_when_tenant_is_empty(): void
    {
        $policy = new SharedMediaPolicy(new PlatformPlanEnforcementService);
        $user = Mockery::mock(AuthUser::class)->makePartial();
        $user->tenant_id = '';
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $this->assertFalse($policy->create($user));
    }

    public function test_create_allows_regular_user_when_plan_allows_upload(): void
    {
        $policy = new SharedMediaPolicy(new PlatformPlanEnforcementService);
        $tenantId = (string) Str::uuid();
        $user = Mockery::mock(AuthUser::class)->makePartial();
        $user->tenant_id = $tenantId;
        $user->shouldReceive('isSuperAdmin')->andReturn(false);

        $this->assertTrue($policy->create($user));
    }

    public function test_view_any_checks_super_admin_and_tenant_presence(): void
    {
        $policy = new SharedMediaPolicy(new PlatformPlanEnforcementService);

        $admin = Mockery::mock(AuthUser::class)->makePartial();
        $admin->tenant_id = '';
        $admin->shouldReceive('isSuperAdmin')->andReturn(true);

        $tenantUser = Mockery::mock(AuthUser::class)->makePartial();
        $tenantUser->tenant_id = (string) Str::uuid();
        $tenantUser->shouldReceive('isSuperAdmin')->andReturn(false);

        $noTenantUser = Mockery::mock(AuthUser::class)->makePartial();
        $noTenantUser->tenant_id = '';
        $noTenantUser->shouldReceive('isSuperAdmin')->andReturn(false);

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->viewAny($tenantUser));
        $this->assertFalse($policy->viewAny($noTenantUser));
    }

    public function test_view_update_and_delete_require_same_tenant_for_regular_user(): void
    {
        $policy = new SharedMediaPolicy(new PlatformPlanEnforcementService);
        $tenantId = (string) Str::uuid();
        $otherTenantId = (string) Str::uuid();

        $user = Mockery::mock(AuthUser::class)->makePartial();
        $user->tenant_id = $tenantId;
        $user->shouldReceive('isSuperAdmin')->times(6)->andReturn(false);

        $mediaSameTenant = new SharedMedia;
        $mediaSameTenant->tenant_id = $tenantId;

        $mediaOtherTenant = new SharedMedia;
        $mediaOtherTenant->tenant_id = $otherTenantId;

        $this->assertTrue($policy->view($user, $mediaSameTenant));
        $this->assertTrue($policy->update($user, $mediaSameTenant));
        $this->assertTrue($policy->delete($user, $mediaSameTenant));

        $this->assertFalse($policy->view($user, $mediaOtherTenant));
        $this->assertFalse($policy->update($user, $mediaOtherTenant));
        $this->assertFalse($policy->delete($user, $mediaOtherTenant));
    }

    public function test_view_update_and_delete_allow_super_admin_regardless_of_tenant(): void
    {
        $policy = new SharedMediaPolicy(new PlatformPlanEnforcementService);

        $admin = Mockery::mock(AuthUser::class)->makePartial();
        $admin->tenant_id = '';
        $admin->shouldReceive('isSuperAdmin')->times(3)->andReturn(true);

        $media = new SharedMedia;
        $media->tenant_id = (string) Str::uuid();

        $this->assertTrue($policy->view($admin, $media));
        $this->assertTrue($policy->update($admin, $media));
        $this->assertTrue($policy->delete($admin, $media));
    }
}
