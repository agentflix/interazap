<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Auth\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthPasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_esqueci_senha_envia_notificacao_e_redefine(): void
    {
        Notification::fake();

        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ])->assertStatus(200);

        $token = null;
        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            }
        );

        $this->assertNotNull($token);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-123', $user->password));
    }

    public function test_forgot_password_returns_success_for_nonexistent_email(): void
    {
        // For security, the endpoint should not reveal if email exists
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ])->assertStatus(200);
    }

    public function test_forgot_password_validates_email_format(): void
    {
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'invalid-email',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_with_invalid_token_does_not_change_password(): void
    {
        $originalPassword = 'original-password';
        $user = AuthUser::factory()->create([
            'password' => Hash::make($originalPassword),
        ]);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        // Regardless of status code, password should NOT have changed
        $user->refresh();
        $this->assertTrue(
            Hash::check($originalPassword, $user->password),
            'Password should not change with invalid token'
        );
    }

    public function test_reset_password_validates_password_confirmation(): void
    {
        $user = AuthUser::factory()->create();

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'some-token',
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
