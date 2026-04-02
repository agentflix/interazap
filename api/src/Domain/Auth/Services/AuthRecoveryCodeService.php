<?php

declare(strict_types=1);

namespace Domain\Auth\Services;

use Domain\Auth\Models\AuthUser;
use JsonException;

/**
 * Serviço para validação e invalidação de códigos de recuperação do 2FA.
 */
final class AuthRecoveryCodeService
{
    /**
     * Verifica se o código de recuperação informado é válido para o usuário.
     */
    public function validate(string $code, AuthUser $user): bool
    {
        return in_array($code, $this->decodeCodes($user), true);
    }

    /**
     * Invalida (remove) um código de recuperação utilizado.
     */
    public function invalidate(string $code, AuthUser $user): void
    {
        $codes = $this->decodeCodes($user);
        $index = array_search($code, $codes, true);

        if ($index === false) {
            return;
        }

        unset($codes[$index]);
        $user->two_factor_recovery_codes = json_encode(array_values($codes), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<int, string>
     */
    private function decodeCodes(AuthUser $user): array
    {
        try {
            $decoded = json_decode((string) ($user->two_factor_recovery_codes ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }
}
