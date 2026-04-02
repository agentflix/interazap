<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Domain\Auth\Actions\AuthUserActions;
use Domain\Auth\DTOs\AuthUserDTO;
use Domain\Auth\DTOs\AuthUserFiltersDTO;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Auth\Repositories\EloquentAuthUserRepository;
use Domain\Auth\Services\AuthAvatarManager;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthUserActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_create_assigns_role_and_hashes_password(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $role = AuthRole::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $actions = new AuthUserActions(new EloquentAuthUserRepository, new AuthAvatarManager);

        $dto = AuthUserDTO::fromArray([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret',
            'tenant_id' => $tenant->id,
            'role' => $role->name,
            'is_active' => true,
        ]);

        $user = $actions->create($dto);

        $this->assertTrue(Hash::check('secret', $user->password));
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_update_changes_password_and_role(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $role = AuthRole::query()->firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('old'),
        ]);

        $actions = new AuthUserActions(new EloquentAuthUserRepository, new AuthAvatarManager);

        $dto = AuthUserDTO::fromArray([
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpass',
            'tenant_id' => $tenant->id,
            'role' => $role->name,
            'is_active' => true,
        ]);

        $updated = $actions->update($user->id, $dto);

        $this->assertTrue(Hash::check('newpass', $updated->password));
        $this->assertTrue($updated->hasRole('manager'));
    }

    public function test_toggle_active_flips_user_status(): void
    {
        $user = AuthUser::factory()->create(['is_active' => true]);

        $actions = new AuthUserActions(new EloquentAuthUserRepository, new AuthAvatarManager);

        $updated = $actions->toggleActive($user->id);

        $this->assertFalse($updated->is_active);
    }

    public function test_list_filters_by_tenant_and_status(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);
        AuthUser::factory()->create([
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);

        $actions = new AuthUserActions(new EloquentAuthUserRepository, new AuthAvatarManager);
        $filters = AuthUserFiltersDTO::fromArray([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $result = $actions->list($filters);

        $this->assertSame(1, $result->total());
        $this->assertSame($tenant->id, $result->items()[0]->tenant_id);
    }

    public function test_delete_removes_user(): void
    {
        $user = AuthUser::factory()->create();

        $actions = new AuthUserActions(new EloquentAuthUserRepository, new AuthAvatarManager);
        $actions->delete($user->id);

        $this->assertSoftDeleted('auth_users', ['id' => $user->id]);
    }

    public function test_update_avatar_uses_avatar_manager(): void
    {
        Storage::fake('public');

        $user = AuthUser::factory()->create();
        $actions = new AuthUserActions(new EloquentAuthUserRepository, new AuthAvatarManager);

        $file = UploadedFile::fake()->image('avatar.jpg');
        $result = $actions->updateAvatar($user->id, $file);

        $this->assertNotEmpty($result['avatar_url']);
        $this->assertNotNull($user->fresh()->avatar_url);
    }

    public function test_delete_avatar_clears_avatar_url(): void
    {
        Storage::fake('public');

        $user = AuthUser::factory()->create([
            'avatar_url' => 'http://localhost/storage/profiles/test/avatar.jpg',
        ]);

        $actions = new AuthUserActions(new EloquentAuthUserRepository, new AuthAvatarManager);
        $result = $actions->deleteAvatar($user->id);

        $this->assertNull($result['avatar_url']);
        $this->assertNull($user->fresh()->avatar_url);
    }

    public function test_create_enforces_unique_email_per_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $role = AuthRole::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        // Create first user
        AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'duplicate@example.com',
        ]);

        $actions = new AuthUserActions(new EloquentAuthUserRepository, new AuthAvatarManager);

        $dto = AuthUserDTO::fromArray([
            'name' => 'Second User',
            'email' => 'duplicate@example.com',
            'password' => 'secret',
            'tenant_id' => $tenant->id,
            'role' => $role->name,
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $actions->create($dto);
    }

    public function test_allows_same_email_in_different_tenants(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();
        $role = AuthRole::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        // Create user in tenant A
        AuthUser::factory()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'shared@example.com',
        ]);

        $actions = new AuthUserActions(new EloquentAuthUserRepository, new AuthAvatarManager);

        // Should be able to create same email in tenant B
        $dto = AuthUserDTO::fromArray([
            'name' => 'User in Tenant B',
            'email' => 'shared@example.com',
            'password' => 'secret',
            'tenant_id' => $tenantB->id,
            'role' => $role->name,
            'is_active' => true,
        ]);

        $user = $actions->create($dto);

        $this->assertSame('shared@example.com', $user->email);
        $this->assertSame($tenantB->id, $user->tenant_id);
    }

    public function test_non_super_admin_cannot_sync_super_admin_role(): void
    {
        $tenantRole = AuthRole::query()->firstOrCreate(
            ['name' => 'inquilino', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        /** @var AuthUser $tenantUser */
        $tenantUser = AuthUser::factory()->create();
        $tenantUser->assignRole($tenantRole);

        /** @var AuthUser $targetUser */
        $targetUser = AuthUser::factory()->create([
            'tenant_id' => $tenantUser->tenant_id,
        ]);

        $this->actingAs($tenantUser, 'sanctum');

        $actions = new AuthUserActions(new EloquentAuthUserRepository, new AuthAvatarManager);

        $this->expectException(AuthorizationException::class);

        $actions->syncRoles((string) $targetUser->id, [AuthRole::SUPER_ADMIN]);
    }
}
