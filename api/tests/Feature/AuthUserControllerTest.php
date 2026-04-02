<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthUserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createAdminUser(): AuthUser
    {
        $role = AuthRole::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user = AuthUser::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_management_endpoints(): void
    {
        Storage::fake('public');

        $admin = $this->createAdminUser();
        $tenantId = (string) $admin->tenant_id;

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/users')
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/auth/users', [
                'tenant_id' => $tenantId,
                'name' => 'User A',
                'email' => 'usera@example.com',
                'password' => 'password',
                'role' => 'admin',
            ])
            ->assertCreated()
            ->assertJsonFragment(['email' => 'usera@example.com']);

        $user = AuthUser::query()->where('email', 'usera@example.com')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/auth/users/{$user->id}", [
                'name' => 'User B',
                'tenant_id' => $tenantId,
                'role' => 'admin',
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'User B']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/auth/users/{$user->id}/toggle")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/auth/users/{$user->id}/avatar", [
                'image' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['avatar_url']]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/auth/users/{$user->id}/avatar")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/auth/users/{$user->id}")
            ->assertNoContent();
    }

    public function test_non_super_admin_cannot_assign_super_admin_role_on_create(): void
    {
        $tenantRole = AuthRole::query()->firstOrCreate(
            ['name' => 'inquilino', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $tenantUser = AuthUser::factory()->create();
        $tenantUser->assignRole($tenantRole);

        $this->actingAs($tenantUser, 'sanctum')
            ->postJson('/api/auth/users', [
                'tenant_id' => (string) $tenantUser->tenant_id,
                'name' => 'Malicious User',
                'email' => 'malicious@example.com',
                'password' => 'password',
                'roles' => ['super-admin'],
            ])
            ->assertForbidden();
    }

    public function test_non_super_admin_cannot_assign_super_admin_role_on_update(): void
    {
        $tenantRole = AuthRole::query()->firstOrCreate(
            ['name' => 'inquilino', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $tenantUser = AuthUser::factory()->create();
        $tenantId = (string) $tenantUser->tenant_id;
        $tenantUser->assignRole($tenantRole);

        $targetUser = AuthUser::factory()->create(['tenant_id' => $tenantId]);

        $this->actingAs($tenantUser, 'sanctum')
            ->putJson("/api/auth/users/{$targetUser->id}", [
                'roles' => ['super-admin'],
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_assign_super_admin_role(): void
    {
        $superAdminRole = AuthRole::query()->firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $superAdmin = AuthUser::factory()->create();
        $superAdmin->assignRole($superAdminRole);

        $tenantId = (string) $superAdmin->tenant_id;

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/auth/users', [
                'tenant_id' => $tenantId,
                'name' => 'Another Super Admin',
                'email' => 'supertest@example.com',
                'password' => 'password',
                'roles' => ['super-admin'],
            ])
            ->assertCreated();
    }

    public function test_non_super_admin_cannot_assign_super_admin_via_singular_role_field_on_create(): void
    {
        $tenantRole = AuthRole::query()->firstOrCreate(
            ['name' => 'inquilino', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $tenantUser = AuthUser::factory()->create();
        $tenantUser->assignRole($tenantRole);

        $this->actingAs($tenantUser, 'sanctum')
            ->postJson('/api/auth/users', [
                'tenant_id' => (string) $tenantUser->tenant_id,
                'name' => 'Malicious User',
                'email' => 'malicious2@example.com',
                'password' => 'password',
                'role' => 'super-admin',
            ])
            ->assertForbidden();
    }

    public function test_non_super_admin_cannot_assign_super_admin_via_singular_role_field_on_update(): void
    {
        $tenantRole = AuthRole::query()->firstOrCreate(
            ['name' => 'inquilino', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $tenantUser = AuthUser::factory()->create();
        $tenantId = (string) $tenantUser->tenant_id;
        $tenantUser->assignRole($tenantRole);

        $targetUser = AuthUser::factory()->create(['tenant_id' => $tenantId]);

        $this->actingAs($tenantUser, 'sanctum')
            ->putJson("/api/auth/users/{$targetUser->id}", [
                'role' => 'super-admin',
            ])
            ->assertForbidden();
    }
}
