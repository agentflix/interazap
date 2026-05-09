<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\AuthPermissionSeeder;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthRoleControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createSuperAdminUser(): AuthUser
    {
        $role = AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::ADMINISTRADOR_ID],
            ['name' => AuthRole::ADMINISTRADOR_NAME, 'guard_name' => 'sanctum']
        );

        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'reports.admin.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $role->givePermissionTo($permission);

        $user = AuthUser::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function seedPermissions(): void
    {
        $this->seed(AuthPermissionSeeder::class);
    }

    public function test_index_returns_paginated_roles(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/roles')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name']],
                'meta' => ['current_page', 'total', 'per_page', 'last_page'],
            ]);
    }

    public function test_store_creates_role_with_permissions(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/auth/roles', [
                'name' => 'supervisor',
                'permissions' => ['users.user.view', 'chat.called.view'],
            ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'supervisor']);

        $role = \Domain\Auth\Models\AuthRole::query()->where('name', 'supervisor')->first();
        $this->assertNotNull($role);
        $this->assertCount(2, $role->permissions);
    }

    public function test_show_returns_single_role(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        $role = AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'viewer',
            'guard_name' => 'sanctum',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/auth/roles/{$role->id}")
            ->assertOk()
            ->assertJsonFragment(['name' => 'viewer']);
    }

    public function test_update_modifies_role_and_syncs_permissions(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        $role = AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'old-name',
            'guard_name' => 'sanctum',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/auth/roles/{$role->id}", [
                'name' => 'new-name',
                'permissions' => ['crm.contact.view'],
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'new-name']);

        $role->refresh();
        $this->assertSame('new-name', $role->name);
        $this->assertCount(1, $role->permissions);
    }

    public function test_destroy_deletes_role(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        $role = AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'deletable',
            'guard_name' => 'sanctum',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/auth/roles/{$role->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('auth_roles', ['id' => $role->id]);
    }

    public function test_destroy_admin_role_is_blocked(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );

        $adminRole = \Domain\Auth\Models\AuthRole::query()->where('id', AuthRole::INQUILINO_ID)->first();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/auth/roles/{$adminRole->id}")
            ->assertForbidden();
    }

    public function test_permissions_endpoint_returns_grouped_permissions(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/roles/permissions')
            ->assertOk();

        $permissions = $response->json('data.permissions');
        $this->assertIsArray($permissions);
        $this->assertArrayHasKey('users', $permissions);
    }

    public function test_store_validates_unique_name(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'duplicate',
            'guard_name' => 'sanctum',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/auth/roles', [
                'name' => 'duplicate',
                'permissions' => [],
            ])
            ->assertUnprocessable();
    }

    public function test_store_validates_permissions_exist(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/auth/roles', [
                'name' => 'test-role',
                'permissions' => ['non.existent.permission'],
            ])
            ->assertUnprocessable();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/auth/roles')
            ->assertUnauthorized();
    }

    public function test_index_supports_search_filter(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'manager',
            'guard_name' => 'sanctum',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/roles?search=manager')
            ->assertOk();

        $data = $response->json('data');
        $this->assertTrue(
            collect($data)->contains(fn ($item): bool => $item['name'] === 'manager')
        );
    }

    public function test_admin_can_view_roles(): void
    {
        $role = AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );
        // Ensure permission exists
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'users.role.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $role->givePermissionTo($permission);

        $admin = AuthUser::factory()->create();
        $admin->assignRole($role);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/roles')
            ->assertOk();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->seedPermissions();
        $user = AuthUser::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/roles')
            ->assertForbidden();
    }

    public function test_index_excludes_super_admin_role_for_non_super_admin_user(): void
    {
        $this->createSuperAdminUser();
        $this->seedPermissions();

        // Create a non-super-admin role and user
        $tenantRole = AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );
        $tenantUser = AuthUser::factory()->create();
        $tenantUser->assignRole($tenantRole);

        $this->actingAs($tenantUser, 'sanctum')
            ->getJson('/api/auth/roles')
            ->assertOk()
            ->assertJsonMissing(['name' => AuthRole::ADMINISTRADOR_NAME]);
    }

    public function test_index_includes_super_admin_role_for_super_admin_user(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => AuthRole::ADMINISTRADOR_NAME]);
    }

    public function test_users_endpoint_returns_paginated_users_for_role(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        $role = AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'test-role-for-users',
            'guard_name' => 'sanctum',
        ]);

        $user1 = AuthUser::factory()->create(['name' => 'Alice', 'tenant_id' => $admin->tenant_id]);
        $user2 = AuthUser::factory()->create(['name' => 'Bob', 'tenant_id' => $admin->tenant_id]);
        $user1->assignRole($role->name);
        $user2->assignRole($role->name);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/auth/roles/{$role->id}/users");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'is_active', 'roles']],
                'meta' => ['current_page', 'total', 'per_page', 'last_page'],
            ]);

        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_users_endpoint_supports_search_filter(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        $role = AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'searchable-role',
            'guard_name' => 'sanctum',
        ]);

        AuthUser::factory()->create(['name' => 'Alice', 'email' => 'alice@test.com', 'tenant_id' => $admin->tenant_id])->assignRole($role->name);
        AuthUser::factory()->create(['name' => 'Bob', 'email' => 'bob@test.com', 'tenant_id' => $admin->tenant_id])->assignRole($role->name);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/auth/roles/{$role->id}/users?search=alice")
            ->assertOk();

        $data = $response->json('data');
        $this->assertTrue(
            collect($data)->contains(fn ($item): bool => $item['name'] === 'Alice')
        );
        $this->assertFalse(
            collect($data)->contains(fn ($item): bool => $item['name'] === 'Bob')
        );
    }

    public function test_users_endpoint_returns_404_for_non_existent_role(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->seedPermissions();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/roles/00000000-0000-0000-0000-000000000000/users')
            ->assertNotFound();
    }

    public function test_users_endpoint_forbidden_without_permission(): void
    {
        $this->seedPermissions();
        $user = AuthUser::factory()->create();

        $role = AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'forbidden-test-role',
            'guard_name' => 'sanctum',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/auth/roles/{$role->id}/users")
            ->assertForbidden();
    }
}
