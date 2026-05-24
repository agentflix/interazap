<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Billing;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingPrefsEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeOwner(PlatformTenant $tenant): AuthUser
    {
        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);

        AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );
        $user->assignRole(AuthRole::INQUILINO_ID);
        $user->refresh();

        return $user;
    }

    #[Test]
    public function it_updates_overage_mode_to_stop(): void
    {
        $plan = PlatformPlan::factory()->create();
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);
        $user = $this->makeOwner($tenant);
        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson('/api/tenants/me/billing-prefs', [
            'overage_mode_override' => 'stop',
        ]);

        $response->assertStatus(200);
        $this->assertSame('stop', $tenant->fresh()->overage_mode_override);
    }

    #[Test]
    public function it_sets_overage_mode_to_null(): void
    {
        $plan = PlatformPlan::factory()->create();
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id, 'overage_mode_override' => 'stop']);
        $user = $this->makeOwner($tenant);
        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson('/api/tenants/me/billing-prefs', [
            'overage_mode_override' => null,
        ]);

        $response->assertStatus(200);
        $this->assertNull($tenant->fresh()->overage_mode_override);
    }

    #[Test]
    public function it_rejects_invalid_mode(): void
    {
        $plan = PlatformPlan::factory()->create();
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);
        $user = $this->makeOwner($tenant);
        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson('/api/tenants/me/billing-prefs', [
            'overage_mode_override' => 'invalid',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_returns_403_for_non_owner(): void
    {
        $plan = PlatformPlan::factory()->create();
        $tenant = PlatformTenant::factory()->create(['plan_id' => $plan->id]);
        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson('/api/tenants/me/billing-prefs', [
            'overage_mode_override' => 'stop',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_returns_401_without_auth(): void
    {
        $this->patchJson('/api/tenants/me/billing-prefs', ['overage_mode_override' => 'stop'])
            ->assertStatus(401);
    }
}
