<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformUserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeSuperAdminWithTenant(PlatformTenant $tenant): AuthUser
    {
        $role = AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::ADMINISTRADOR_ID],
            ['name' => AuthRole::ADMINISTRADOR_NAME, 'guard_name' => 'sanctum']
        );

        $superAdmin = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $superAdmin->assignRole($role);

        return $superAdmin->refresh();
    }

    public function test_platform_store_respects_selected_company_for_super_admin_with_tenant(): void
    {
        $superAdminTenant = PlatformTenant::factory()->create();
        $selectedTenant = PlatformTenant::factory()->create();
        $superAdmin = $this->makeSuperAdminWithTenant($superAdminTenant);

        AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::GERENTE_ID],
            ['name' => AuthRole::GERENTE_NAME, 'guard_name' => 'sanctum']
        );

        Sanctum::actingAs($superAdmin, abilities: ['*']);

        $response = $this->postJson('/api/platform/users', [
            'tenant_id' => $selectedTenant->id,
            'company_id' => $selectedTenant->id,
            'name' => 'Usuário Cross Tenant',
            'email' => 'cross-tenant@example.com',
            'password' => 'password123',
            'roles' => [AuthRole::GERENTE_NAME],
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.tenant_id', $selectedTenant->id);

        $this->assertDatabaseHas('auth_users', [
            'email' => 'cross-tenant@example.com',
            'tenant_id' => $selectedTenant->id,
        ]);
    }

}
