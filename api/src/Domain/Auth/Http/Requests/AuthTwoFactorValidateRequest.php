<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para ativar 2FA após setup.
 */
final class AuthTwoFactorValidateRequest extends FormRequest
{
    /** Permite apenas usuários autenticados. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Regras de validação para ativação do 2FA (código TOTP de 6 dígitos).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
