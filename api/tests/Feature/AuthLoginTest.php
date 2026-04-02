<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_login_retorna_token_e_permite_acessar_perfil(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        Log::spy();

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token', 'permissions']]);

        $token = $loginResponse->json('data.token');
        $this->assertNotEmpty($token);

        $meResponse = $this->withToken($token)->getJson('/api/auth/me');
        $meResponse->assertStatus(200)
            ->assertJsonPath('data.user.email', $user->email);

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $message === 'auth.login.success'
                && ($context['user_id'] ?? null) === $user->id
                && ($context['tenant_id'] ?? null) === $user->tenant_id
                && ($context['two_factor'] ?? null) === false);
    }

    public function test_rota_protegida_exige_autenticacao(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong_password',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_rejects_nonexistent_user(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_logout_revokes_token(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $token = $loginResponse->json('data.token');

        // Verify token was created
        $this->assertDatabaseHas('auth_personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        // Logout
        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        // Token should be deleted from database
        $this->assertDatabaseMissing('auth_personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_refresh_returns_new_token(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $oldToken = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->json('data.token');

        // Count tokens before refresh
        $tokenCountBefore = $user->tokens()->count();
        $this->assertEquals(1, $tokenCountBefore);

        // Refresh token
        $response = $this->withToken($oldToken)
            ->postJson('/api/auth/refresh')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $newToken = $response->json('data.token');

        // Tokens should be different
        $this->assertNotEquals($oldToken, $newToken);

        // Should still have only 1 token (old deleted, new created)
        $user->refresh();
        $tokenCountAfter = $user->tokens()->count();
        $this->assertEquals(1, $tokenCountAfter);

        // New token should work
        $this->withToken($newToken)
            ->getJson('/api/auth/me')
            ->assertStatus(200);
    }

    public function test_get_menu_returns_menu_items(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->json('data.token');

        $this->withToken($token)
            ->getJson('/api/auth/get-menu')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_validates_email_format(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'invalid-email',
            'password' => 'password',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_locks_account_after_five_failed_attempts(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'wrong_password',
            ])->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        }

        $this->travel(2)->minutes();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong_password',
        ])->assertStatus(429)
            ->assertJsonValidationErrors(['email']);

        $this->travelBack();
    }

    public function test_login_unlocks_after_lockout_window(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'wrong_password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong_password',
        ])->assertStatus(429);

        $this->travel(16)->minutes();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->travelBack();
    }
}
