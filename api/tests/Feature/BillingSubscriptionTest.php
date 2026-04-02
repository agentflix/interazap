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

class BillingSubscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_subscription_returns_current_plan_and_usage(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $plan = PlatformPlan::factory()->create([
            'name' => 'Pro',
            'slug' => 'pro-'.Str::lower(Str::random(6)),
            'price_monthly' => 199,
            'limit_users' => 5,
            'chat_channels_limit' => 2,
        ]);

        $tenant->plan_id = $plan->id;
        $tenant->save();

        Sanctum::actingAs($admin, abilities: ['*']);

        $response = $this->getJson('/api/billing/subscription');

        $response->assertOk();
        $response->assertJsonPath('data.current_plan.id', $plan->id);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'current_plan',
                'usage' => ['users', 'instances', 'storage', 'negotiations', 'ai'],
                'next_invoice',
            ],
        ]);
    }

    public function test_non_admin_user_cannot_access_subscription_endpoints(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        AuthPermission::query()->firstOrCreate(
            ['name' => 'billing.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user->givePermissionTo('billing.view');

        Sanctum::actingAs($user, abilities: ['*']);

        $this->getJson('/api/billing/subscription')->assertForbidden();
        $this->getJson('/api/billing/plans')->assertForbidden();
    }

    private function createTenantAdmin(): array
    {
        $tenant = PlatformTenant::factory()->create();

        $admin = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => $tenant->primary_email,
        ]);

        $role = AuthRole::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        foreach (['billing.view', 'billing.plan.manage'] as $permission) {
            AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::orderedUuid()]
            );
        }

        $admin->assignRole($role);
        $admin->givePermissionTo(['billing.view', 'billing.plan.manage']);

        return [$tenant->refresh(), $admin->refresh()];
    }
}
