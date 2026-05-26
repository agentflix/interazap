<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para upload de avatar de usuário (admin).
 */
final class AuthUserAvatarRequest extends FormRequest
{
    /** Permite apenas usuários autenticados. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Regras de validação para upload de avatar de usuário (admin).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:2048'],
        ];
    }
}
