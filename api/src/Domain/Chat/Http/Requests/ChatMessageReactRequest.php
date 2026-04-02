<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para reação de mensagem.
 *
 * Define as regras para reagir ou remover reação (emoji).
 *
 * @category Requests
 */
final class ChatMessageReactRequest extends FormRequest
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
            'emoji' => ['nullable', 'string', 'max:32'],
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
            'emoji.max' => 'O emoji deve ter no máximo 32 caracteres.',
        ];
    }
}
