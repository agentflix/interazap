<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para edição de mensagem.
 *
 * Define as regras para atualização do conteúdo textual.
 *
 * @category Requests
 */
final class ChatMessageEditRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * Regras de validação.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:4096'],
        ];
    }

    /**
     * Mensagens de erro personalizadas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => 'O conteúdo é obrigatório.',
            'content.max' => 'O conteúdo deve ter no máximo 4096 caracteres.',
        ];
    }
}
