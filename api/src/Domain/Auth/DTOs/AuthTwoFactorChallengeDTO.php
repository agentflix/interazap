<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

/**
 * DTO que indica que o usuário precisa completar o desafio de 2FA.
 *
 * @readonly
 */
final readonly class AuthTwoFactorChallengeDTO
{
    public function __construct(public string $email) {}

    /**
     * Serializa o desafio 2FA para array de resposta.
     *
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
