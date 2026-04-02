<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Domain\Auth\Actions\AuthTwoFactorActions;
use Domain\Auth\Models\AuthUser;
use Domain\Auth\Services\AuthTotpService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @group auth
 * @group two-factor
 */
class AuthTwoFactorActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthTwoFactorActions $actions;

    private AuthTotpService $totpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totpService = new AuthTotpService;
        $this->actions = new AuthTwoFactorActions($this->totpService);
    }

    public function test_get_status_returns_false_when_not_enabled(): void
    {
        $user = AuthUser::factory()->create([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        $status = $this->actions->getStatus($user);

        expect($status)->toMatchArray([
            'enabled' => false,
            'has_recovery_codes' => false,
        ]);
    }

    public function test_get_status_returns_true_when_enabled(): void
    {
        $user = AuthUser::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_secret' => 'secret123',
            'two_factor_recovery_codes' => json_encode(['code1', 'code2']),
        ]);

        $status = $this->actions->getStatus($user);

        expect($status)->toMatchArray([
            'enabled' => true,
            'has_recovery_codes' => true,
        ]);
    }

    public function test_setup_generates_secret_and_qr_code_url(): void
    {
        $user = AuthUser::factory()->create([
            'email' => 'test@example.com',
            'two_factor_enabled' => false,
        ]);

        $result = $this->actions->setup($user);

        expect($result)
            ->toHaveKey('secret')
            ->toHaveKey('qr_code_url');

        expect($result['secret'])->toBeString()->toHaveLength(32);
        expect($result['qr_code_url'])->toContain('otpauth://totp/');
        expect($result['qr_code_url'])->toContain('Agentflix');
        expect($result['qr_code_url'])->toContain(urlencode('test@example.com'));

        $user->refresh();
        // O secret é criptografado no banco, usa getDecryptedSecret para comparar
        expect($this->actions->getDecryptedSecret($user))->toBe($result['secret']);
        expect($user->two_factor_enabled)->toBeFalse();
    }

    public function test_setup_throws_exception_if_already_enabled(): void
    {
        $user = AuthUser::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_secret' => 'existing_secret',
        ]);

        expect(fn (): array => $this->actions->setup($user))
            ->toThrow(ValidationException::class);
    }

    public function test_validate_enables_2fa_and_returns_recovery_codes(): void
    {
        $secret = $this->totpService->generateSecret();

        $user = AuthUser::factory()->create([
            'two_factor_enabled' => false,
            'two_factor_secret' => Crypt::encryptString($secret),
        ]);

        $result = $this->actions->validate($user, $this->totpService->currentCode($secret));

        expect($result)
            ->toHaveKey('recovery_codes')
            ->and($result['recovery_codes'])->toBeArray()->toHaveCount(8);

        $user->refresh();
        expect($user->two_factor_enabled)->toBeTrue();
        expect($user->two_factor_recovery_codes)->not->toBeNull();

        $recoveryCodes = json_decode((string) $user->two_factor_recovery_codes, true);
        expect($recoveryCodes)->toHaveCount(8);
    }

    public function test_validate_throws_exception_if_secret_not_set(): void
    {
        $user = AuthUser::factory()->create([
            'two_factor_secret' => null,
        ]);

        expect(fn (): array => $this->actions->validate($user, '123456'))
            ->toThrow(ValidationException::class, '2FA não foi configurado');
    }

    public function test_validate_throws_exception_for_invalid_code_format(): void
    {
        $secret = $this->totpService->generateSecret();

        $user = AuthUser::factory()->create([
            'two_factor_secret' => Crypt::encryptString($secret),
        ]);

        expect(fn (): array => $this->actions->validate($user, '123'))
            ->toThrow(ValidationException::class, 'Código inválido');

        expect(fn (): array => $this->actions->validate($user, 'abcdef'))
            ->toThrow(ValidationException::class, 'Código inválido');

        expect(fn (): array => $this->actions->validate($user, '12345678'))
            ->toThrow(ValidationException::class, 'Código inválido');
    }

    public function test_disable_requires_correct_password(): void
    {
        $password = 'correct_password';
        $user = AuthUser::factory()->create([
            'password' => Hash::make($password),
            'two_factor_enabled' => true,
            'two_factor_secret' => 'secret',
            'two_factor_recovery_codes' => json_encode(['code1']),
        ]);

        $this->actions->disable($user, $password);

        $user->refresh();
        expect($user->two_factor_enabled)->toBeFalse();
        expect($user->two_factor_secret)->toBeNull();
        expect($user->two_factor_recovery_codes)->toBeNull();
    }

    public function test_disable_throws_exception_for_wrong_password(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('correct_password'),
            'two_factor_enabled' => true,
        ]);

        expect(fn () => $this->actions->disable($user, 'wrong_password'))
            ->toThrow(ValidationException::class, 'Senha incorreta');

        $user->refresh();
        expect($user->two_factor_enabled)->toBeTrue();
    }

    public function test_regenerate_recovery_codes_creates_new_codes(): void
    {
        $password = 'test_password';
        $user = AuthUser::factory()->create([
            'password' => Hash::make($password),
            'two_factor_enabled' => true,
            'two_factor_secret' => 'secret',
            'two_factor_recovery_codes' => json_encode(['old_code']),
        ]);

        $oldCodes = json_decode((string) $user->two_factor_recovery_codes, true);

        $newCodes = $this->actions->regenerateRecoveryCodes($user, $password);

        expect($newCodes)
            ->toBeArray()
            ->toHaveCount(8)
            ->not->toEqual($oldCodes);

        $user->refresh();
        $savedCodes = json_decode((string) $user->two_factor_recovery_codes, true);
        expect($savedCodes)->toEqual($newCodes);
    }

    public function test_regenerate_recovery_codes_throws_exception_if_not_enabled(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('password'),
            'two_factor_enabled' => false,
        ]);

        expect(fn (): array => $this->actions->regenerateRecoveryCodes($user, 'password'))
            ->toThrow(ValidationException::class, '2FA não está ativado');
    }

    public function test_regenerate_recovery_codes_throws_exception_for_wrong_password(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('correct_password'),
            'two_factor_enabled' => true,
        ]);

        expect(fn (): array => $this->actions->regenerateRecoveryCodes($user, 'wrong_password'))
            ->toThrow(ValidationException::class, 'Senha incorreta');
    }

    public function test_tenant_isolation_in_disable(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();
        $password = 'password';

        $userA = AuthUser::factory()->create([
            'tenant_id' => $tenantA->id,
            'password' => Hash::make($password),
            'two_factor_enabled' => true,
        ]);

        $userB = AuthUser::factory()->create([
            'tenant_id' => $tenantB->id,
            'password' => Hash::make($password),
            'two_factor_enabled' => true,
        ]);

        $this->actions->disable($userA, $password);

        $userA->refresh();
        $userB->refresh();

        expect($userA->two_factor_enabled)->toBeFalse();
        expect($userB->two_factor_enabled)->toBeTrue();
    }

    // ===============================================
    // 2FA Encryption Tests (Security Audit 2026-01-30)
    // ===============================================

    public function test_setup_stores_encrypted_secret(): void
    {
        $user = AuthUser::factory()->create([
            'email' => 'encrypt-test@example.com',
            'two_factor_enabled' => false,
        ]);

        $result = $this->actions->setup($user);
        $plaintextSecret = $result['secret'];

        $user->refresh();

        // O secret no banco NÃO deve ser igual ao plaintext
        expect($user->two_factor_secret)->not->toBe($plaintextSecret);

        // O secret no banco deve ser uma string criptografada (base64-like)
        expect($user->two_factor_secret)->toBeString();
        expect(strlen((string) $user->two_factor_secret))->toBeGreaterThan(32);
    }

    public function test_encrypted_secret_can_be_decrypted_correctly(): void
    {
        $user = AuthUser::factory()->create([
            'email' => 'decrypt-test@example.com',
            'two_factor_enabled' => false,
        ]);

        $result = $this->actions->setup($user);
        $plaintextSecret = $result['secret'];

        $user->refresh();

        // Descriptografar e comparar com o original
        $decrypted = $this->actions->getDecryptedSecret($user);

        expect($decrypted)->toBe($plaintextSecret);
    }

    public function test_qr_code_url_contains_plaintext_secret(): void
    {
        $user = AuthUser::factory()->create([
            'email' => 'qr-test@example.com',
            'two_factor_enabled' => false,
        ]);

        $result = $this->actions->setup($user);

        // QR code deve conter o secret em plaintext para funcionar com apps de autenticação
        expect($result['qr_code_url'])->toContain($result['secret']);
    }

    public function test_get_decrypted_secret_returns_null_when_no_secret(): void
    {
        $user = AuthUser::factory()->create([
            'two_factor_secret' => null,
        ]);

        $decrypted = $this->actions->getDecryptedSecret($user);

        expect($decrypted)->toBeNull();
    }

    public function test_get_decrypted_secret_throws_on_invalid_encrypted_value(): void
    {
        $user = AuthUser::factory()->create([
            'two_factor_secret' => 'invalid_not_encrypted_value',
        ]);

        expect(fn (): ?string => $this->actions->getDecryptedSecret($user))
            ->toThrow(\Illuminate\Contracts\Encryption\DecryptException::class);
    }
}
