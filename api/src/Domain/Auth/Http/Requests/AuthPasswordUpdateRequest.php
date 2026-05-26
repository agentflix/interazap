<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para troca de senha do usuário autenticado.
 */
final class AuthPasswordUpdateRequest extends FormRequest
{
    /** Permite apenas usuários autenticados. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Regras de validação para troca de senha com confirmação da senha atual.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
