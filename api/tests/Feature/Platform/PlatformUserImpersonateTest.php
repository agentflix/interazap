<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses()->group('platform', 'impersonation');

beforeEach(function (): void {
    $this->superAdminRole = AuthRole::firstOrCreate(
        ['id' => AuthRole::ADMINISTRADOR_ID, 'name' => AuthRole::ADMINISTRADOR_NAME, 'guard_name' => 'sanctum']
    );

    $this->managerRole = AuthRole::firstOrCreate(
        ['id' => AuthRole::GERENTE_ID, 'name' => AuthRole::GERENTE_NAME, 'guard_name' => 'sanctum'],
        ['id' => (string) \Illuminate\Support\Str::orderedUuid()]
    );

    $this->tenant = PlatformTenant::factory()->create(['is_active' => true]);

    $this->superAdminTenant = PlatformTenant::factory()->create(['is_active' => true]);

    $this->superAdmin = AuthUser::factory()->create([
        'tenant_id' => $this->superAdminTenant->id,
        'password' => Hash::make('super-secret'),
        'is_active' => true,
    ]);
    $this->superAdmin->assignRole($this->superAdminRole);

    $this->targetUser = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('target-secret'),
        'is_active' => true,
    ]);
    $this->targetUser->assignRole($this->managerRole);

    $this->regularUser = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('user-secret'),
        'is_active' => true,
    ]);
});

test('super admin can impersonate an active user', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    $response = $this->postJson("/api/platform/users/{$this->targetUser->id}/impersonate", [
        'password' => 'super-secret',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.is_impersonating', true)
        ->assertJsonPath('data.user.id', $this->targetUser->id)
        ->assertJsonPath('data.user.tenant_id', $this->targetUser->tenant_id)
        ->assertJsonPath('data.impersonated_by.id', $this->superAdmin->id)
        ->assertJsonStructure([
            'data' => [
                'user',
                'permissions',
                'token',
                'is_impersonating',
                'impersonated_by' => ['id', 'name', 'email'],
                'impersonated_tenant' => ['id', 'name'],
            ],
        ]);
});

test('non super admin cannot impersonate user', function (): void {
    Sanctum::actingAs($this->regularUser, abilities: ['*']);

    $response = $this->postJson("/api/platform/users/{$this->targetUser->id}/impersonate", [
        'password' => 'user-secret',
    ]);

    $response->assertForbidden();
});

test('impersonation fails with incorrect password', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    $response = $this->postJson("/api/platform/users/{$this->targetUser->id}/impersonate", [
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('impersonation fails for inactive user', function (): void {
    $inactiveUser = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('inactive-secret'),
        'is_active' => false,
    ]);

    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    $response = $this->postJson("/api/platform/users/{$inactiveUser->id}/impersonate", [
        'password' => 'super-secret',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['user']);
});

test('impersonation fails for user of inactive tenant', function (): void {
    $inactiveTenant = PlatformTenant::factory()->create(['is_active' => false]);

    $userOfInactiveTenant = AuthUser::factory()->create([
        'tenant_id' => $inactiveTenant->id,
        'password' => Hash::make('user-secret'),
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    $response = $this->postJson("/api/platform/users/{$userOfInactiveTenant->id}/impersonate", [
        'password' => 'super-secret',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['user']);
});

test('super admin cannot self-impersonate', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    $response = $this->postJson("/api/platform/users/{$this->superAdmin->id}/impersonate", [
        'password' => 'super-secret',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['user']);
});

test('impersonated user has correct tenant context', function (): void {
    Sanctum::actingAs($this->superAdmin, abilities: ['*']);

    $response = $this->postJson("/api/platform/users/{$this->targetUser->id}/impersonate", [
        'password' => 'super-secret',
    ]);

    $response->assertOk();

    $impersonatedTenantId = $response->json('data.impersonated_tenant.id');
    expect($impersonatedTenantId)->toBe((string) $this->targetUser->tenant_id);
});
