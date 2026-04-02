<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\AuthPermissionSeeder;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthRbacTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rota_protegida_respeita_permissoes(): void
    {
        Artisan::call('db:seed', ['--class' => AuthPermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        Sanctum::actingAs($user, abilities: ['*']);
        $this->getJson('/api/auth/users')->assertStatus(403);

        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'users.user.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user->givePermissionTo($permission);
        $adminRole = AuthRole::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->assignRole($adminRole);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->refresh();
        $this->assertTrue($user->can('users.user.view'));
        $this->assertTrue(Gate::forUser($user)->inspect('viewAny', AuthUser::class)->allowed());

        Sanctum::actingAs($user, abilities: ['*']);
        $response = $this->getJson('/api/auth/users');

        $response->assertStatus(200);
    }

    public function test_should_assign_role_to_user(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = AuthRole::query()->firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user = AuthUser::factory()->create();

        $this->assertFalse($user->hasRole('manager'));

        $user->assignRole($role);
        $user->refresh();

        $this->assertTrue($user->hasRole('manager'));
    }

    public function test_should_check_permission_correctly(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'crm.contact.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user = AuthUser::factory()->create();

        $this->assertFalse($user->can('crm.contact.view'));

        $user->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->refresh();

        $this->assertTrue($user->can('crm.contact.view'));
    }

    public function test_should_inherit_role_permissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create permissions
        $viewPermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'crm.contact.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $createPermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'crm.contact.create', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        // Create role with permissions
        $role = AuthRole::query()->firstOrCreate(
            ['name' => 'crm_agent', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $role->givePermissionTo([$viewPermission, $createPermission]);

        // Create user and assign role
        $user = AuthUser::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->refresh();

        // User should inherit role permissions
        $this->assertTrue($user->hasRole('crm_agent'));
        $this->assertTrue($user->can('crm.contact.view'));
        $this->assertTrue($user->can('crm.contact.create'));
        $this->assertFalse($user->can('crm.contact.delete'));
    }

    public function test_user_without_role_has_no_inherited_permissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = AuthUser::factory()->create();

        $this->assertFalse($user->can('crm.contact.view'));
        $this->assertFalse($user->can('users.user.view'));
        $this->assertFalse($user->can('chat.called.view'));
    }

    public function test_removing_role_removes_inherited_permissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'reports.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $role = AuthRole::query()->firstOrCreate(
            ['name' => 'reporter', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $role->givePermissionTo($permission);

        $user = AuthUser::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->refresh();
        $this->assertTrue($user->can('reports.view'));

        // Remove role
        $user->removeRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->refresh();

        $this->assertFalse($user->hasRole('reporter'));
        $this->assertFalse($user->can('reports.view'));
    }
}
