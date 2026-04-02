<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class BillingDelinquencyMiddlewareTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'billing.delinquency'])
            ->get('/api/test/delinquency/protected', static fn () => response()->json(['ok' => true]));

        Route::middleware(['api', 'auth:sanctum', 'billing.delinquency'])
            ->get('/api/test/delinquency/allowed', static fn () => response()->json(['ok' => true]));

        config()->set('billing.delinquency.lockout.allowed_routes', ['api/test/delinquency/allowed']);
        config()->set('billing.delinquency.enabled', true);
    }

    public function test_locked_tenant_gets_423_on_protected_route(): void
    {
        [$tenant, $user] = $this->createAuthenticatedTenant(BillingTenantStatus::LOCKED);

        BillingInvoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'overdue',
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        $response = $this->getJson('/api/test/delinquency/protected');

        $response->assertStatus(423)
            ->assertJsonPath('error', 'tenant_locked')
            ->assertJsonStructure(['overdue_invoices', 'purge_deadline']);
    }

    public function test_locked_tenant_can_access_whitelisted_route(): void
    {
        $this->createAuthenticatedTenant(BillingTenantStatus::LOCKED);

        $this->getJson('/api/test/delinquency/allowed')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_active_tenant_passes_through(): void
    {
        $this->createAuthenticatedTenant(BillingTenantStatus::ACTIVE);

        $this->getJson('/api/test/delinquency/protected')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_grace_tenant_passes_through(): void
    {
        $this->createAuthenticatedTenant(BillingTenantStatus::GRACE);

        $this->getJson('/api/test/delinquency/protected')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_feature_flag_off_bypasses_lockout(): void
    {
        config()->set('billing.delinquency.enabled', false);

        $this->createAuthenticatedTenant(BillingTenantStatus::LOCKED);

        $this->getJson('/api/test/delinquency/protected')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    /**
     * @return array{PlatformTenant, AuthUser}
     */
    private function createAuthenticatedTenant(BillingTenantStatus $status): array
    {
        $tenant = PlatformTenant::factory()->create([
            'billing_status' => $status,
            'billing_purge_deadline' => now()->addDays(10)->toDateString(),
        ]);

        $user = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => $tenant->primary_email,
        ]);

        Sanctum::actingAs($user, abilities: ['*']);

        return [$tenant, $user];
    }
}
