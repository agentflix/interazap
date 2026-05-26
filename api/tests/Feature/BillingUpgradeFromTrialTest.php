<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Models\TenantMessageUsage;
use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class BillingUpgradeFromTrialTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformPlan $trialPlan;

    private PlatformPlan $paidPlan;

    private PlatformTenant $tenant;

    private AuthUser $admin;

    private $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );

        foreach (['billing.view', 'billing.plan.manage'] as $perm) {
            AuthPermission::query()->firstOrCreate(
                ['name' => $perm, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::orderedUuid()]
            );
        }

        $this->trialPlan = PlatformPlan::factory()->create([
            'slug' => 'trial',
            'is_trial' => true,
            'is_active' => true,
            'price_monthly' => 0.00,
            'cycle_days' => 7,
            'message_limit_monthly' => 100,
        ]);

        $this->paidPlan = PlatformPlan::factory()->create([
            'slug' => 'pro',
            'is_trial' => false,
            'is_active' => true,
            'price_monthly' => 297.00,
            'cycle_days' => 30,
            'message_limit_monthly' => 5000,
        ]);

        $this->gateway = Mockery::mock(BillingGatewayService::class);
        $this->app->instance(BillingGatewayService::class, $this->gateway);

        $this->tenant = PlatformTenant::factory()->create([
            'plan_id' => $this->trialPlan->id,
            'has_used_trial' => false,
            'asaas_customer_id' => 'cust-test-001',
        ]);

        $this->admin = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $this->tenant->primary_email,
        ]);

        $this->admin->assignRole(AuthRole::INQUILINO_ID);
        $this->admin->givePermissionTo(['billing.view', 'billing.plan.manage']);
        $this->admin->refresh();

        // Seed a trial usage row
        TenantMessageUsage::create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
            'cycle_start' => CarbonImmutable::today()->toDateString(),
            'cycle_end' => CarbonImmutable::today()->addDays(7)->toDateString(),
            'message_count' => 42,
            'overage_count' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_upgrade_troca_plano_quando_charge_confirmado(): void
    {
        $this->gateway->shouldReceive('ensureCustomer')->andReturn('cust-test-001');
        $this->gateway->shouldReceive('createPaymentWithToken')->andReturn([
            'paymentId' => 'pay-001',
            'status' => 'CONFIRMED',
            'brand' => 'VISA',
            'last4' => '4242',
        ]);
        $this->gateway->shouldReceive('getLastError')->andReturn(null)->byDefault();

        Sanctum::actingAs($this->admin, abilities: ['*']);

        $response = $this->postJson('/api/billing/upgrade-from-trial', [
            'plan_id' => $this->paidPlan->id,
            'card_token' => 'tok_test_visa_4242',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.success', true);

        $this->tenant->refresh();
        $this->assertSame($this->paidPlan->id, $this->tenant->plan_id);
        $this->assertTrue($this->tenant->has_used_trial);
        $this->assertSame('VISA', $this->tenant->payment_method_brand);
        $this->assertSame('4242', $this->tenant->payment_method_last4);
    }

    public function test_upgrade_rejeita_se_plano_nao_e_trial(): void
    {
        $this->tenant->update(['plan_id' => $this->paidPlan->id]);

        Sanctum::actingAs($this->admin, abilities: ['*']);

        $this->postJson('/api/billing/upgrade-from-trial', [
            'plan_id' => $this->paidPlan->id,
            'card_token' => 'tok_test',
        ])->assertStatus(422);
    }

    public function test_upgrade_rejeita_se_trial_ja_foi_utilizado(): void
    {
        $this->tenant->update(['has_used_trial' => true]);

        $this->gateway->shouldReceive('ensureCustomer')->andReturn('cust-test-001');

        Sanctum::actingAs($this->admin, abilities: ['*']);

        $this->postJson('/api/billing/upgrade-from-trial', [
            'plan_id' => $this->paidPlan->id,
            'card_token' => 'tok_test',
        ])->assertStatus(422);
    }

    public function test_upgrade_rejeita_charge_recusado(): void
    {
        $this->gateway->shouldReceive('ensureCustomer')->andReturn('cust-test-001');
        $this->gateway->shouldReceive('createPaymentWithToken')->andReturn([
            'paymentId' => null,
            'status' => null,
            'brand' => null,
            'last4' => null,
        ]);
        $this->gateway->shouldReceive('getLastError')->andReturn('Cartão recusado');

        Sanctum::actingAs($this->admin, abilities: ['*']);

        $this->postJson('/api/billing/upgrade-from-trial', [
            'plan_id' => $this->paidPlan->id,
            'card_token' => 'tok_declined',
        ])->assertStatus(500);

        // Plan must not change
        $this->tenant->refresh();
        $this->assertSame($this->trialPlan->id, $this->tenant->plan_id);
        $this->assertFalse($this->tenant->has_used_trial);
    }

    public function test_upgrade_cria_nova_linha_de_uso_para_plano_novo(): void
    {
        $this->gateway->shouldReceive('ensureCustomer')->andReturn('cust-test-001');
        $this->gateway->shouldReceive('createPaymentWithToken')->andReturn([
            'paymentId' => 'pay-002',
            'status' => 'CONFIRMED',
            'brand' => 'MASTERCARD',
            'last4' => '5555',
        ]);
        $this->gateway->shouldReceive('getLastError')->andReturn(null)->byDefault();

        Sanctum::actingAs($this->admin, abilities: ['*']);

        $usageCountBefore = TenantMessageUsage::query()
            ->where('tenant_id', $this->tenant->id)
            ->count();

        $this->postJson('/api/billing/upgrade-from-trial', [
            'plan_id' => $this->paidPlan->id,
            'card_token' => 'tok_mc_5555',
        ])->assertStatus(200);

        $usageCountAfter = TenantMessageUsage::query()
            ->where('tenant_id', $this->tenant->id)
            ->count();

        $this->assertGreaterThan($usageCountBefore, $usageCountAfter);
    }
}
