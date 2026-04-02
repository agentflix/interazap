<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para Respostas Rápidas (Quick Answers).
 *
 * Define as regras para criação de modelos de resposta agilizada,
 * validando o conteúdo textual e identificadores de atalho.
 *
 * @category Requests
 */
class ChatQuickAnswerRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     *
     * @return bool True se autorizado.
     */
    public function authorize(): bool
    {
        return $this->user()->can('chat.quicks.manage');
    }

    /**
     * Define as regras de validação que se aplicam à requisição.
     *
     * @return array<string, string|array<int, string>> Regras de validação.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'shortcut' => ['nullable', 'string', 'max:50'],
            'content' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
