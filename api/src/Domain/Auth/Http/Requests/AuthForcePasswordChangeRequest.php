<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para troca de senha forçada (sem senha atual).
 */
final class AuthForcePasswordChangeRequest extends FormRequest
{
    /** Permite apenas usuários autenticados. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Regras de validação para troca de senha forçada.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
