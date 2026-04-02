<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();

    // Criar role sem permissões
    $this->userRole = AuthRole::create(['name' => 'user', 'guard_name' => 'sanctum']);

    // Mock HTTP calls to gateway to prevent real network requests
    Http::fake([
        'localhost:3000/*' => Http::response(['status' => 'ok'], 200),
        '*/uazapi/*' => Http::response(['status' => 'ok'], 200),
    ]);
});

test('user without permission receives 403 on billing invoice create', function (): void {
    $user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($this->userRole);

    // Use Sanctum::actingAs with empty abilities to simulate token without permissions
    Sanctum::actingAs($user, []);

    $this->postJson('/api/billing/invoices', [
        'reference_month' => '2026-01',
        'amount' => 100.00,
        'due_date' => now()->addDays(30)->format('Y-m-d'),
    ])
        ->assertForbidden();
});

test('user without permission receives 403 on billing invoice update', function (): void {
    $user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($this->userRole);

    Sanctum::actingAs($user, []);

    $this->putJson('/api/billing/invoices/00000000-0000-0000-0000-000000000001', [
        'amount' => 200.00,
    ])
        ->assertForbidden();
});

test('tenant user can create crm contact without explicit permission', function (): void {
    $user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($this->userRole);

    // CRM contacts don't require explicit permission - just auth
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/crm/contacts', [
        'name' => 'Test Contact',
    ])
        ->assertCreated();
});

test('user without permission receives 403 on chat ticket create', function (): void {
    $user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($this->userRole);

    Sanctum::actingAs($user, []);

    $this->postJson('/api/chat/tickets', [
        'channel' => 'whatsapp',
    ])
        ->assertForbidden();
});

test('user without permission receives 403 on billing invoice list', function (): void {
    $user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($this->userRole);

    Sanctum::actingAs($user, []);

    $this->getJson('/api/billing/invoices')
        ->assertForbidden();
});

test('unauthenticated user cannot update profile', function (): void {
    $this->putJson('/api/auth/profile', ['name' => 'New Name'])
        ->assertUnauthorized();
});

test('user without whatsapp.manage cannot create uazapi instance', function (): void {
    $user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($this->userRole);

    Sanctum::actingAs($user, []);

    $this->postJson('/api/platform/uazapi/instances', ['name' => 'Test'])
        ->assertForbidden();
});
