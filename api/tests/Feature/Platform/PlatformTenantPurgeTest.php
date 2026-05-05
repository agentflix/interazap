<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingPaymentStatus;
use Domain\Billing\Models\BillingPayment;
use Domain\Chat\Models\ChatInstance;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->actorTenant = PlatformTenant::factory()->create();

    $adminRole = AuthRole::query()->firstOrCreate(
        ['name' => 'admin', 'guard_name' => 'sanctum'],
        ['id' => (string) Str::orderedUuid()]
    );

    $this->actor = AuthUser::factory()->create([
        'tenant_id' => $this->actorTenant->id,
        'password' => Hash::make('correct-password'),
    ]);
    $this->actor->assignRole($adminRole);
    $this->actingAs($this->actor);
});

test('purge tenant with correct password returns 204', function (): void {
    $targetTenant = PlatformTenant::factory()->create();
    ChatInstance::factory()->create(['tenant_id' => $targetTenant->id]);
    CRMContact::factory()->create(['tenant_id' => $targetTenant->id]);

    $response = $this->deleteJson("/api/platform/tenants/{$targetTenant->id}/purge", [
        'password' => 'correct-password',
    ]);

    $response->assertNoContent();

    $this->assertDatabaseMissing('platform_tenants', ['id' => $targetTenant->id]);
    $this->assertDatabaseMissing('chat_instances', ['tenant_id' => $targetTenant->id]);
    $this->assertDatabaseMissing('crm_contacts', ['tenant_id' => $targetTenant->id]);
});

test('purge tenant with incorrect password returns 403', function (): void {
    $targetTenant = PlatformTenant::factory()->create();

    $response = $this->deleteJson("/api/platform/tenants/{$targetTenant->id}/purge", [
        'password' => 'wrong-password',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('platform_tenants', ['id' => $targetTenant->id]);
});

test('purge tenant without authentication returns 401', function (): void {
    $this->app['auth']->forgetGuards();
    $targetTenant = PlatformTenant::factory()->create();

    $response = $this->deleteJson("/api/platform/tenants/{$targetTenant->id}/purge", [
        'password' => 'correct-password',
    ]);

    $response->assertUnauthorized();
});

test('purge tenant without password returns 422', function (): void {
    $targetTenant = PlatformTenant::factory()->create();

    $response = $this->deleteJson("/api/platform/tenants/{$targetTenant->id}/purge", []);

    $response->assertUnprocessable();
});

test('purge tenant blocked by recent payment', function (): void {
    $targetTenant = PlatformTenant::factory()->create();
    BillingPayment::factory()->create([
        'tenant_id' => $targetTenant->id,
        'confirmed_at' => now()->subDays(5),
        'status' => BillingPaymentStatus::CONFIRMED,
    ]);

    $response = $this->deleteJson("/api/platform/tenants/{$targetTenant->id}/purge", [
        'password' => 'correct-password',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('platform_tenants', ['id' => $targetTenant->id]);
});

test('purge tenant blocked by super admin user', function (): void {
    $targetTenant = PlatformTenant::factory()->create();
    $superAdmin = AuthUser::factory()->create(['tenant_id' => $targetTenant->id]);
    $superAdminRole = AuthRole::query()->firstOrCreate(
        ['name' => 'super-admin', 'guard_name' => 'sanctum'],
        ['id' => (string) Str::orderedUuid()]
    );
    $superAdmin->assignRole($superAdminRole);

    $response = $this->deleteJson("/api/platform/tenants/{$targetTenant->id}/purge", [
        'password' => 'correct-password',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('platform_tenants', ['id' => $targetTenant->id]);
});
