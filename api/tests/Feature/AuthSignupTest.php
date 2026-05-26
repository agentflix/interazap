<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\AiPromptMasterSeeder;
use Database\Seeders\AiPromptSegmentSeeder;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class AuthSignupTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformPlan $trialPlan;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure Inquilino role exists
        AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );

        // Create trial plan
        $this->trialPlan = PlatformPlan::factory()->create([
            'slug' => 'trial',
            'is_trial' => true,
            'is_active' => true,
            'price_monthly' => 0.00,
            'cycle_days' => 7,
            'message_limit_monthly' => 100,
        ]);

        // Seed bootstrap data required by PlatformTenantBootstrapAction
        $this->seed(AiPromptMasterSeeder::class);
        $this->seed(AiPromptSegmentSeeder::class);

        // Mock gateway — signup must never block on Asaas failure
        $gateway = Mockery::mock(BillingGatewayService::class);
        $gateway->shouldReceive('ensureCustomer')->andReturn('asaas-cust-123')->byDefault();
        $this->app->instance(BillingGatewayService::class, $gateway);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_signup_cria_tenant_user_e_retorna_token(): void
    {
        $response = $this->postJson('/api/auth/signup', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'secret123',
            'accept_terms' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token', 'permissions', 'tenant_plan']]);

        $this->assertDatabaseHas('auth_users', ['email' => 'joao@example.com']);
        $this->assertDatabaseHas('platform_tenants', ['primary_email' => 'joao@example.com']);

        $user = AuthUser::query()->where('email', 'joao@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(AuthRole::INQUILINO_NAME));
    }

    public function test_signup_associa_plano_trial_ao_tenant(): void
    {
        $this->postJson('/api/auth/signup', [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'password' => 'secret123',
            'accept_terms' => true,
        ])->assertStatus(201);

        $user = AuthUser::query()->where('email', 'maria@example.com')->firstOrFail();
        $tenant = PlatformTenant::query()->find($user->tenant_id);

        $this->assertNotNull($tenant);
        $this->assertSame($this->trialPlan->id, $tenant->plan_id);
    }

    public function test_signup_rejeita_email_duplicado(): void
    {
        AuthUser::factory()->create(['email' => 'duplicate@example.com']);

        $this->postJson('/api/auth/signup', [
            'name' => 'Outro User',
            'email' => 'duplicate@example.com',
            'password' => 'secret123',
            'accept_terms' => true,
        ])->assertStatus(422);
    }

    public function test_signup_rejeita_sem_accept_terms(): void
    {
        $this->postJson('/api/auth/signup', [
            'name' => 'Sem Termos',
            'email' => 'noterms@example.com',
            'password' => 'secret123',
            'accept_terms' => false,
        ])->assertStatus(422);
    }

    public function test_signup_rejeita_senha_curta(): void
    {
        $this->postJson('/api/auth/signup', [
            'name' => 'Senha Curta',
            'email' => 'short@example.com',
            'password' => '123',
            'accept_terms' => true,
        ])->assertStatus(422);
    }

    public function test_signup_falha_graciosamente_se_asaas_indisponivel(): void
    {
        $gateway = Mockery::mock(BillingGatewayService::class);
        $gateway->shouldReceive('ensureCustomer')->andThrow(new \RuntimeException('Asaas offline'));
        $this->app->instance(BillingGatewayService::class, $gateway);

        // Signup should still succeed even if Asaas fails
        $this->postJson('/api/auth/signup', [
            'name' => 'Resiliente Silva',
            'email' => 'resilient@example.com',
            'password' => 'secret123',
            'accept_terms' => true,
        ])->assertStatus(201);

        $this->assertDatabaseHas('auth_users', ['email' => 'resilient@example.com']);
    }

    public function test_signup_retorna_tenant_plan_com_is_trial_true(): void
    {
        $response = $this->postJson('/api/auth/signup', [
            'name' => 'Trial User',
            'email' => 'trial@example.com',
            'password' => 'secret123',
            'accept_terms' => true,
        ])->assertStatus(201);

        $this->assertTrue($response->json('data.tenant_plan.is_trial'));
    }
}
