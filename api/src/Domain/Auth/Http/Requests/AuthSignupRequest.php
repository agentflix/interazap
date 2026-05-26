<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do payload de signup público.
 *
 * Aceita cadastro via formulário tradicional (name + email + password + accept_terms).
 * O campo `accept_terms` é obrigatório e deve ser true.
 */
final class AuthSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'min:2'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'accept_terms' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome completo é obrigatório.',
            'name.min' => 'O nome deve ter pelo menos 2 caracteres.',
            'email.required' => 'O email é obrigatório.',
            'email.email' => 'Informe um email válido.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'accept_terms.required' => 'Você deve aceitar os termos de uso.',
            'accept_terms.accepted' => 'Você deve aceitar os termos de uso.',
        ];
    }
}
