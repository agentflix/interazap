<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para solicitação de redefinição de senha.
 */
final class AuthForgotPasswordRequest extends FormRequest
{
    /** Endpoint público — sempre autorizado. */
    public function authorize(): bool
    {
        // Public endpoint
        return true;
    }

    /**
     * Regras de validação para solicitação de redefinição.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
