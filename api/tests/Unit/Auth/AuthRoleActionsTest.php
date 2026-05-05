<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Domain\Auth\Actions\AuthRoleActions;
use Domain\Auth\DTOs\AuthRoleDTO;
use Domain\Auth\DTOs\AuthRoleFiltersDTO;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthRoleActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function seedPermissions(): void
    {
        $permissions = [
            'users.user.view',
            'users.user.manage',
            'users.role.view',
            'users.role.manage',
            'chat.called.view',
            'crm.contact.view',
        ];

        foreach ($permissions as $name) {
            AuthPermission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::uuid()]
            );
        }
    }

    public function test_create_role_with_permissions(): void
    {
        $this->seedPermissions();
        $actions = new AuthRoleActions;

        $dto = AuthRoleDTO::fromArray([
            'name' => 'supervisor',
            'permissions' => ['users.user.view', 'chat.called.view'],
        ]);

        $role = $actions->create($dto);

        $this->assertSame('supervisor', $role->name);
        $this->assertCount(2, $role->permissions);
        $this->assertTrue($role->permissions->pluck('name')->contains('users.user.view'));
        $this->assertTrue($role->permissions->pluck('name')->contains('chat.called.view'));
    }

    public function test_create_role_without_permissions(): void
    {
        $actions = new AuthRoleActions;

        $dto = AuthRoleDTO::fromArray([
            'name' => 'empty-role',
            'permissions' => [],
        ]);

        $role = $actions->create($dto);

        $this->assertSame('empty-role', $role->name);
        $this->assertCount(0, $role->permissions);
    }

    public function test_update_syncs_permissions(): void
    {
        $this->seedPermissions();
        $actions = new AuthRoleActions;

        $role = AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'test-role',
            'guard_name' => 'sanctum',
        ]);

        $perm = \Domain\Auth\Models\AuthPermission::query()->where('name', 'users.user.view')->first();
        $role->syncPermissions([$perm]);

        $dto = AuthRoleDTO::fromArray([
            'name' => 'updated-role',
            'permissions' => ['crm.contact.view', 'chat.called.view'],
        ]);

        $updated = $actions->update($role->id, $dto);

        $this->assertSame('updated-role', $updated->name);
        $this->assertCount(2, $updated->permissions);
        $this->assertFalse($updated->permissions->pluck('name')->contains('users.user.view'));
        $this->assertTrue($updated->permissions->pluck('name')->contains('crm.contact.view'));
    }

    public function test_delete_removes_role(): void
    {
        $role = AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'deletable',
            'guard_name' => 'sanctum',
        ]);

        $actions = new AuthRoleActions;
        $actions->delete($role->id);

        $this->assertDatabaseMissing('auth_roles', ['id' => $role->id]);
    }

    public function test_delete_admin_role_is_blocked(): void
    {
        AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );

        $adminRole = \Domain\Auth\Models\AuthRole::query()->where('id', AuthRole::INQUILINO_ID)->first();
        $actions = new AuthRoleActions;

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $actions->delete($adminRole->id);
    }

    public function test_list_returns_paginated_roles(): void
    {
        AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );

        AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'viewer',
            'guard_name' => 'sanctum',
        ]);

        $actions = new AuthRoleActions;
        $filters = AuthRoleFiltersDTO::fromArray([]);

        $result = $actions->list($filters);

        $this->assertGreaterThanOrEqual(2, $result->total());
    }

    public function test_list_filters_by_search(): void
    {
        AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'manager-searchable',
            'guard_name' => 'sanctum',
        ]);

        $actions = new AuthRoleActions;
        $filters = AuthRoleFiltersDTO::fromArray(['search' => 'manager-searchable']);

        $result = $actions->list($filters);

        $this->assertSame(1, $result->total());
        $this->assertSame('manager-searchable', $result->items()[0]->name);
    }

    public function test_find_returns_role_with_permissions(): void
    {
        $this->seedPermissions();

        $role = AuthRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'findable',
            'guard_name' => 'sanctum',
        ]);

        $perm = \Domain\Auth\Models\AuthPermission::query()->where('name', 'users.user.view')->first();
        $role->syncPermissions([$perm]);

        $actions = new AuthRoleActions;
        $found = $actions->find($role->id);

        $this->assertSame('findable', $found->name);
        $this->assertCount(1, $found->permissions);
    }

    public function test_all_permissions_returns_grouped(): void
    {
        $this->seedPermissions();

        $actions = new AuthRoleActions;
        $grouped = $actions->allPermissions();

        $this->assertArrayHasKey('users', $grouped->toArray());
        $this->assertArrayHasKey('chat', $grouped->toArray());
        $this->assertArrayHasKey('crm', $grouped->toArray());
    }
}
