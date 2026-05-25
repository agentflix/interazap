<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PlatformTenantControllerTest extends TestCase
{
    private function makeAdmin(): AuthUser
    {
        $role = AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::ADMINISTRADOR_ID],
            ['name' => AuthRole::ADMINISTRADOR_NAME, 'guard_name' => 'sanctum']
        );

        $admin = AuthUser::factory()->create();
        $admin->assignRole($role);

        return $admin->refresh();
    }

    public function test_update_requires_plan_id(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $tenant = PlatformTenant::factory()->create();

        $this->putJson("/api/platform/tenants/{$tenant->id}", [
            'name' => 'Tenant Sem Plano',
            'is_active' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id'])
            ->assertJsonPath('errors.plan_id.0', 'O plano é obrigatório.');
    }

    public function test_update_rejects_null_plan_id(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $tenant = PlatformTenant::factory()->create();

        $this->putJson("/api/platform/tenants/{$tenant->id}", [
            'name' => 'Tenant Plano Nulo',
            'plan_id' => null,
            'is_active' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);
    }

    public function test_update_with_valid_plan_id_updates_tenant(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin, abilities: ['*']);

        $plan = PlatformPlan::factory()->create();
        $tenant = PlatformTenant::factory()->create([
            'plan_id' => $plan->id,
            'is_active' => true,
        ]);

        $this->putJson("/api/platform/tenants/{$tenant->id}", [
            'name' => 'Tenant Atualizado',
            'plan_id' => $plan->id,
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Tenant Atualizado')
            ->assertJsonPath('data.plan_id', $plan->id)
            ->assertJsonPath('data.is_active', false);

        $tenant->refresh();

        $this->assertSame('Tenant Atualizado', $tenant->name);
        $this->assertSame($plan->id, $tenant->plan_id);
        $this->assertFalse($tenant->is_active);
    }
}
