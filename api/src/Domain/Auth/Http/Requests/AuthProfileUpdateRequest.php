<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualização de perfil do usuário autenticado.
 */
final class AuthProfileUpdateRequest extends FormRequest
{
    /** Permite apenas usuários autenticados. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Regras de validação para atualização de perfil.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'avatar_url' => ['nullable', 'url'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
