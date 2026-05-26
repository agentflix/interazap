<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para upload de avatar do próprio usuário.
 */
final class AuthProfileImageRequest extends FormRequest
{
    /** Permite apenas usuários autenticados. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Regras de validação para upload de imagem de perfil.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required_without:avatar', 'image', 'max:2048'],
            'avatar' => ['required_without:image', 'image', 'max:2048'],
        ];
    }
}
