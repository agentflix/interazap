<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();

    $adminRole = AuthRole::query()->firstOrCreate(
        ['name' => 'admin', 'guard_name' => 'sanctum'],
        ['id' => (string) Str::orderedUuid()]
    );

    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($adminRole);
    $this->actingAs($this->user);
});

test('can fetch tenant details with plan and resources', function (): void {
    $plan = PlatformPlan::factory()->create([
        'name' => 'Pro',
        'slug' => 'pro',
        'limit_users' => 10,
        'whatsapp_integrations_limit' => 3,
        'storage_mode' => 'LIMITED',
        'storage_limit_bytes' => 5368709120,
        'ai_enabled' => true,
        'negotiations_mode' => 'UNLIMITED',
        'negotiations_limit' => null,
        'price_monthly' => 199.00,
        'is_active' => true,
    ]);

    BillingInvoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    AuthUser::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson("/api/platform/tenants/{$this->tenant->id}/details");

    $response->assertOk()
        ->assertJsonPath('data.company.id', $this->tenant->id)
        ->assertJsonPath('data.company.name', $this->tenant->name)
        ->assertJsonPath('data.contracted_plan.name', 'Pro')
        ->assertJsonPath('data.contracted_plan.slug', 'pro')
        ->assertJsonPath('data.resources.users.limit', 10)
        ->assertJsonPath('data.resources.instances.limit', 3)
        ->assertJsonPath('data.resources.storage.mode', 'LIMITED')
        ->assertJsonPath('data.resources.ai.enabled', true)
        ->assertJsonPath('data.resources.negotiations.mode', 'UNLIMITED');

    expect($response->json('data.resources.users.current'))->toBeInt();
    expect($response->json('data.resources.users.available'))->toBeInt();
});

test('tenant details returns null plan when not defined', function (): void {
    $response = $this->getJson("/api/platform/tenants/{$this->tenant->id}/details");

    $response->assertOk()
        ->assertJsonPath('data.company.name', $this->tenant->name)
        ->assertJsonPath('data.contracted_plan', null)
        ->assertJsonPath('data.resources.ai.enabled', true)
        ->assertJsonPath('data.resources.users.limit', null)
        ->assertJsonPath('data.resources.users.available', null);
});

test('tenant details requires admin role', function (): void {
    $regularUser = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($regularUser);

    $response = $this->getJson("/api/platform/tenants/{$this->tenant->id}/details");

    $response->assertForbidden();
});

test('tenant details returns 404 for nonexistent tenant', function (): void {
    $response = $this->getJson('/api/platform/tenants/00000000-0000-0000-0000-000000000000/details');

    $response->assertNotFound();
});

test('tenant details calculates available resources correctly', function (): void {
    $plan = PlatformPlan::factory()->create([
        'limit_users' => 5,
        'whatsapp_integrations_limit' => 2,
        'storage_mode' => 'LIMITED',
        'storage_limit_bytes' => 1073741824,
        'negotiations_mode' => 'LIMITED',
        'negotiations_limit' => 100,
        'is_active' => true,
    ]);

    BillingInvoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    AuthUser::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $response = $this->getJson("/api/platform/tenants/{$this->tenant->id}/details");

    $response->assertOk();

    $usersData = $response->json('data.resources.users');
    expect($usersData['limit'])->toBe(5);
    expect($usersData['current'])->toBeGreaterThanOrEqual(3);
    expect($usersData['available'])->toBe($usersData['limit'] - $usersData['current']);

    $storageData = $response->json('data.resources.storage');
    expect($storageData['mode'])->toBe('LIMITED');
    expect($storageData['limit_bytes'])->toBe(1073741824);
    expect((float) $storageData['limit_gb'])->toBe(1.0);
});
