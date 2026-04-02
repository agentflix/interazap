<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Auth\Services\AuthTotpService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuthTwoFactorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_fluxo_2fa_completo(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->json('data.token');

        // Setup
        $setup = $this->withToken($token)->postJson('/api/auth/2fa/setup')->assertStatus(200);
        $secret = $setup->json('data.secret');
        $this->assertNotEmpty($secret);

        // Validate setup
        $totp = app(AuthTotpService::class);
        $validate = $this->withToken($token)->postJson('/api/auth/2fa/validate', [
            'code' => $totp->currentCode((string) $secret),
        ])->assertStatus(200);
        $recoveryCodes = $validate->json('data.recovery_codes');
        $this->assertIsArray($recoveryCodes);
        $this->assertCount(8, $recoveryCodes);

        $user->refresh();
        $this->assertTrue((bool) $user->two_factor_enabled);

        // Login agora exige desafio 2FA
        Log::spy();
        $challenge = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertStatus(200);

        $challenge->assertJsonPath('data.email', $user->email);

        // Login com código de recuperação
        $code = $recoveryCodes[0];
        $session = $this->postJson('/api/auth/login-with-2fa', [
            'email' => $user->email,
            'code' => $code,
        ])->assertStatus(200);

        $this->assertNotEmpty($session->json('data.token'));

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $message === 'auth.login.success' && ($context['two_factor'] ?? null) === true);
    }
}
