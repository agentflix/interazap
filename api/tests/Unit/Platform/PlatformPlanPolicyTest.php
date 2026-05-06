<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Policies\PlatformPlanPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PlatformPlanPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_policy_blocks_non_admin(): void
    {
        $user = AuthUser::factory()->create();
        $policy = new PlatformPlanPolicy;
        $plan = PlatformPlan::factory()->create();

        $this->assertFalse($policy->create($user));
        $this->assertFalse($policy->update($user, $plan));
    }

    public function test_policy_allows_admin(): void
    {
        $user = AuthUser::factory()->create();
        $user->assignRole(AuthRole::INQUILINO_ID);

        $policy = new PlatformPlanPolicy;
        $plan = PlatformPlan::factory()->create();

        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $plan));
    }
}
