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
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthGoogleCallbackTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformPlan $trialPlan;

    protected function setUp(): void
    {
        parent::setUp();

        AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );

        $this->trialPlan = PlatformPlan::factory()->create([
            'slug' => 'trial',
            'is_trial' => true,
            'is_active' => true,
            'price_monthly' => 0.00,
            'cycle_days' => 7,
        ]);

        // Seed bootstrap data required by PlatformTenantBootstrapAction
        $this->seed(AiPromptMasterSeeder::class);
        $this->seed(AiPromptSegmentSeeder::class);

        $gateway = Mockery::mock(BillingGatewayService::class);
        $gateway->shouldReceive('ensureCustomer')->andReturn('asaas-cust-abc')->byDefault();
        $this->app->instance(BillingGatewayService::class, $gateway);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockSocialiteUser(string $id, string $email, string $name): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn($id);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getName')->andReturn($name);

        $provider = Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        $factory = Mockery::mock(SocialiteFactory::class);
        $factory->shouldReceive('driver')->with('google')->andReturn($provider);

        $this->app->instance(SocialiteFactory::class, $factory);
    }

    public function test_google_callback_cria_novo_tenant_para_email_novo(): void
    {
        $this->mockSocialiteUser('google-123', 'novo@example.com', 'Novo User');

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();

        $this->assertDatabaseHas('auth_users', [
            'email' => 'novo@example.com',
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);

        $user = AuthUser::query()->where('email', 'novo@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);

        $tenant = PlatformTenant::query()->find($user->tenant_id);
        $this->assertSame($this->trialPlan->id, $tenant->plan_id);
    }

    public function test_google_callback_vincula_provider_para_email_existente(): void
    {
        $existingUser = AuthUser::factory()->create([
            'email' => 'existing@example.com',
            'provider' => null,
            'provider_id' => null,
        ]);

        $this->mockSocialiteUser('google-456', 'existing@example.com', 'Existing User');

        $this->get('/api/auth/google/callback');

        $existingUser->refresh();
        $this->assertSame('google', $existingUser->provider);
        $this->assertSame('google-456', $existingUser->provider_id);
    }

    public function test_google_callback_faz_login_se_provider_id_ja_existe(): void
    {
        $existingUser = AuthUser::factory()->create([
            'email' => 'linked@example.com',
            'provider' => 'google',
            'provider_id' => 'google-789',
        ]);

        $this->mockSocialiteUser('google-789', 'linked@example.com', 'Linked User');

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();

        // User count should remain the same — no new user created
        $count = AuthUser::query()->where('email', 'linked@example.com')->count();
        $this->assertSame(1, $count);
    }

    public function test_google_callback_redireciona_com_token(): void
    {
        $this->mockSocialiteUser('google-999', 'redirect@example.com', 'Redirect User');

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('token=', (string) $location);
    }
}
