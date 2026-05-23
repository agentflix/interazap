<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingOwnerProtectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_downgrade_preserves_owner_user(): void
    {
        $tenant = PlatformTenant::factory()->create([
            'primary_email' => 'owner@example.com',
        ]);

        $role = AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );

        foreach (['billing.view', 'billing.plan.manage'] as $permission) {
            AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::orderedUuid()]
            );
        }

        $owner = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $owner->assignRole($role);
        $owner->givePermissionTo(['billing.view', 'billing.plan.manage']);

        AuthUser::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $premium = PlatformPlan::factory()->create([
            'slug' => 'premium-'.Str::lower(Str::random(6)),
            'price_monthly' => 300,
            'limit_users' => 10,
        ]);

        $starter = PlatformPlan::factory()->create([
            'slug' => 'starter-'.Str::lower(Str::random(6)),
            'price_monthly' => 50,
            'limit_users' => 1,
        ]);

        $tenant->plan_id = $premium->id;
        $tenant->save();

        Sanctum::actingAs($owner, abilities: ['*']);

        $this->postJson('/api/billing/plan-change', [
            'plan_id' => $starter->id,
            'current_password' => 'password',
        ])->assertOk();

        $owner->refresh();
        $this->assertTrue((bool) $owner->is_active);
    }
}
