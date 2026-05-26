<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do payload de login tradicional.
 */
final class AuthLoginRequest extends FormRequest
{
    /** Endpoint público — sempre autorizado. */
    public function authorize(): bool
    {
        // Public endpoint
        return true;
    }

    /**
     * Regras de validação para login com email e senha.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'remember' => ['boolean'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
