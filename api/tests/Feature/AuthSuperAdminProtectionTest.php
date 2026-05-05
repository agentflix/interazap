<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthSuperAdminProtectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_super_admin_cannot_be_deleted(): void
    {
        [$admin, $superAdmin] = $this->createActors();

        Sanctum::actingAs($admin, abilities: ['*']);

        $this->deleteJson('/api/auth/users/'.$superAdmin->id)
            ->assertForbidden();
    }

    public function test_super_admin_cannot_be_deactivated(): void
    {
        [$admin, $superAdmin] = $this->createActors();

        Sanctum::actingAs($admin, abilities: ['*']);

        $this->postJson('/api/auth/users/'.$superAdmin->id.'/toggle')
            ->assertForbidden();
    }

    private function createActors(): array
    {
        $tenant = PlatformTenant::factory()->create();

        $adminRole = AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );

        $superRole = AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::ADMINISTRADOR_ID],
            ['name' => AuthRole::ADMINISTRADOR_NAME, 'guard_name' => 'sanctum']
        );

        $manageUsers = AuthPermission::query()->firstOrCreate(
            ['name' => 'users.user.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $admin = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole($adminRole);
        $admin->givePermissionTo($manageUsers);

        $superAdmin = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $superAdmin->assignRole($superRole);

        return [$admin->refresh(), $superAdmin->refresh()];
    }
}
