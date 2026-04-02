<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

/**
 * DTO indicating user needs to complete 2FA challenge.
 *
 * @readonly
 */
final readonly class AuthTwoFactorChallengeDTO
{
    public function __construct(public string $email) {}

    /**
     * @return array{email:string,two_factor_required:bool}
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'two_factor_required' => true,
        ];
    }
}
