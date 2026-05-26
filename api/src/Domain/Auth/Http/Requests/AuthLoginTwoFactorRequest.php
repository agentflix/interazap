<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do payload de desafio 2FA.
 */
final class AuthLoginTwoFactorRequest extends FormRequest
{
    /** Endpoint público — sempre autorizado. */
    public function authorize(): bool
    {
        // Public endpoint
        return true;
    }

    /**
     * Regras de validação para o desafio 2FA.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'min:4'],
        ];
    }
}
