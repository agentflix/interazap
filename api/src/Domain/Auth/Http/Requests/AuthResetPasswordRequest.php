<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para redefinição de senha via token.
 */
final class AuthResetPasswordRequest extends FormRequest
{
    /** Endpoint público — sempre autorizado. */
    public function authorize(): bool
    {
        // Public endpoint
        return true;
    }

    /**
     * Regras de validação para redefinição de senha com token.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'token' => ['required', 'string'],
        ];
    }
}
