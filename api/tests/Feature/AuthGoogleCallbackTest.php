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
            'slug'          => 'trial',
            'is_trial'      => true,
            'is_active'     => true,
            'price_monthly' => 0.00,
            'cycle_days'    => 7,
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
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        $factory = Mockery::mock(SocialiteFactory::class);
        $factory->shouldReceive('driver')->with('google')->andReturn($provider);

        $this->app->instance(SocialiteFactory::class, $factory);
    }

    private function signupState(): string
    {
        return json_encode(['intent' => 'signup']);
    }

    private function loginState(): string
    {
        return json_encode(['intent' => 'login']);
    }

    // ---------------------------------------------------------------------------
    // signup intent
    // ---------------------------------------------------------------------------

    public function test_google_callback_cria_novo_tenant_para_email_novo(): void
    {
        $this->mockSocialiteUser('google-123', 'novo@example.com', 'Novo User');

        $response = $this->get('/api/auth/google/callback?state='.urlencode($this->signupState()));

        $response->assertRedirect();

        $this->assertDatabaseHas('auth_users', [
            'email'       => 'novo@example.com',
            'provider'    => 'google',
            'provider_id' => 'google-123',
        ]);

        $user = AuthUser::query()->where('email', 'novo@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);

        $tenant = PlatformTenant::query()->find($user->tenant_id);
        $this->assertSame($this->trialPlan->id, $tenant->plan_id);
    }

    public function test_google_callback_redireciona_com_token(): void
    {
        $this->mockSocialiteUser('google-999', 'redirect@example.com', 'Redirect User');

        $response = $this->get('/api/auth/google/callback?state='.urlencode($this->signupState()));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('token=', (string) $location);
    }

    // ---------------------------------------------------------------------------
    // login intent
    // ---------------------------------------------------------------------------

    public function test_google_callback_faz_login_se_provider_id_ja_existe(): void
    {
        AuthUser::factory()->create([
            'email'       => 'linked@example.com',
            'provider'    => 'google',
            'provider_id' => 'google-789',
        ]);

        $this->mockSocialiteUser('google-789', 'linked@example.com', 'Linked User');

        $response = $this->get('/api/auth/google/callback?state='.urlencode($this->loginState()));

        $response->assertRedirect();

        $count = AuthUser::query()->where('email', 'linked@example.com')->count();
        $this->assertSame(1, $count);
    }

    public function test_login_rejeita_email_nao_cadastrado(): void
    {
        $this->mockSocialiteUser('google-aaa', 'naoexiste@example.com', 'Ghost User');

        $response = $this->get('/api/auth/google/callback?state='.urlencode($this->loginState()));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('error=not_registered', (string) $location);

        $this->assertDatabaseMissing('auth_users', ['email' => 'naoexiste@example.com']);
    }

    public function test_login_rejeita_email_sem_google_vinculado(): void
    {
        AuthUser::factory()->create([
            'email'       => 'semgoogle@example.com',
            'provider'    => null,
            'provider_id' => null,
        ]);

        $this->mockSocialiteUser('google-bbb', 'semgoogle@example.com', 'No Google User');

        $response = $this->get('/api/auth/google/callback?state='.urlencode($this->loginState()));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('error=not_linked', (string) $location);

        // Provider must NOT have been auto-linked
        $user = AuthUser::query()->where('email', 'semgoogle@example.com')->firstOrFail();
        $this->assertNull($user->provider);
    }

    // ---------------------------------------------------------------------------
    // signup intent — duplicate rejection
    // ---------------------------------------------------------------------------

    public function test_signup_rejeita_email_ja_cadastrado(): void
    {
        AuthUser::factory()->create([
            'email'       => 'jaexiste@example.com',
            'provider'    => null,
            'provider_id' => null,
        ]);

        $this->mockSocialiteUser('google-ccc', 'jaexiste@example.com', 'Existing User');

        $response = $this->get('/api/auth/google/callback?state='.urlencode($this->signupState()));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('error=already_registered', (string) $location);
    }

    // ---------------------------------------------------------------------------
    // link intent
    // ---------------------------------------------------------------------------

    public function test_google_callback_vincula_provider_ao_user_autenticado(): void
    {
        $existingUser = AuthUser::factory()->create([
            'email'       => 'existing@example.com',
            'provider'    => null,
            'provider_id' => null,
        ]);

        $state = json_encode(['intent' => 'link', 'user_id' => $existingUser->id]);

        $this->mockSocialiteUser('google-456', 'existing@example.com', 'Existing User');

        $response = $this->get('/api/auth/google/callback?state='.urlencode($state));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('linked=true', (string) $location);

        $existingUser->refresh();
        $this->assertSame('google', $existingUser->provider);
        $this->assertSame('google-456', $existingUser->provider_id);
    }

    public function test_link_rejeita_provider_ja_vinculado_a_outro_user(): void
    {
        AuthUser::factory()->create([
            'email'       => 'outro@example.com',
            'provider'    => 'google',
            'provider_id' => 'google-dup',
        ]);

        $targetUser = AuthUser::factory()->create([
            'email'       => 'target@example.com',
            'provider'    => null,
            'provider_id' => null,
        ]);

        $state = json_encode(['intent' => 'link', 'user_id' => $targetUser->id]);

        $this->mockSocialiteUser('google-dup', 'target@example.com', 'Target User');

        $response = $this->get('/api/auth/google/callback?state='.urlencode($state));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('error=already_linked', (string) $location);
    }
}
