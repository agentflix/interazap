<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\Models\AuthUser;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Fluxo de recuperação de senha (envio e redefinição).
 */
final class AuthPasswordResetActions
{
    /**
     * Envia link de redefinição de senha para o e-mail informado.
     *
     * @return string Constante do Password broker: Password::RESET_LINK_SENT,
     *                Password::INVALID_USER ou Password::RESET_THROTTLED.
     */
    public function sendResetLink(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }

    /**
     * Redefine a senha do usuário usando o token enviado por e-mail.
     *
     * @param  array<string, mixed>  $payload  Dados com email, password, password_confirmation e token.
     * @return string Constante do Password broker: Password::PASSWORD_RESET ou erro equivalente.
     */
    public function reset(array $payload): string
    {
        return Password::reset(
            [
                'email' => $payload['email'],
                'password' => $payload['password'],
                'password_confirmation' => $payload['password_confirmation'] ?? $payload['password'],
                'token' => $payload['token'],
            ],
            function (AuthUser $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );
    }
}
