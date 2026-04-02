<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\Models\AuthUser;
use Domain\Auth\Services\AuthTotpService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Casos de uso de autenticação em dois fatores.
 *
 * SECURITY: two_factor_secret é armazenado criptografado no banco usando Crypt::encryptString().
 * Isso protege contra bypass de 2FA caso o banco seja comprometido.
 */
final class AuthTwoFactorActions
{
    private const int RECOVERY_CODES_COUNT = 8;

    public function __construct(private readonly AuthTotpService $totpService) {}

    /**
     * @return array<string, mixed>
     */
    public function getStatus(AuthUser $user): array
    {
        return [
            'enabled' => (bool) ($user->two_factor_enabled ?? false),
            'has_recovery_codes' => ! empty($user->two_factor_recovery_codes),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function setup(AuthUser $user): array
    {
        if ($user->two_factor_enabled) {
            throw ValidationException::withMessages([
                '2fa' => ['2FA já está ativado.'],
            ]);
        }

        $secret = $this->totpService->generateSecret();

        // SECURITY: Armazena secret criptografado no banco
        $user->two_factor_secret = Crypt::encryptString($secret);
        $user->save();

        return [
            'secret' => $secret,
            'qr_code_url' => $this->totpService->generateOtpAuthUrl($user->email, $secret),
        ];
    }

    /**
     * Descriptografa o two_factor_secret do usuário.
     *
     * @throws DecryptException Se o valor não puder ser descriptografado
     */
    public function getDecryptedSecret(AuthUser $user): ?string
    {
        if ($user->two_factor_secret === null) {
            return null;
        }

        return Crypt::decryptString($user->two_factor_secret);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(AuthUser $user, string $code): array
    {
        if ($user->two_factor_secret === null) {
            throw ValidationException::withMessages([
                '2fa' => ['2FA não foi configurado. Execute setup primeiro.'],
            ]);
        }

        $secret = $this->getDecryptedSecret($user);

        if ($secret === null || ! $this->totpService->verify($secret, $code)) {
            throw ValidationException::withMessages([
                'code' => ['Código inválido.'],
            ]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->two_factor_enabled = true;
        $user->two_factor_recovery_codes = json_encode($recoveryCodes, JSON_THROW_ON_ERROR);
        $user->save();

        return [
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /**
     * @throws ValidationException
     */
    public function disable(AuthUser $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Senha incorreta.'],
            ]);
        }

        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->save();
    }

    /**
     * @return array<int, string>
     *
     * @throws ValidationException
     */
    public function regenerateRecoveryCodes(AuthUser $user, string $password): array
    {
        if (! $user->two_factor_enabled) {
            throw ValidationException::withMessages([
                '2fa' => ['2FA não está ativado.'],
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Senha incorreta.'],
            ]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->two_factor_recovery_codes = json_encode($recoveryCodes, JSON_THROW_ON_ERROR);
        $user->save();

        return $recoveryCodes;
    }

    /**
     * @return array<int, string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODES_COUNT; $i++) {
            $codes[] = Str::random(10);
        }

        return $codes;
    }
}
