<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para Templates de Mensagem.
 *
 * Define as regras para criação de modelos de mensagem pré-definidos,
 * validando campos como nome, conteúdo e atalhos de busca.
 *
 * @category Requests
 */
class ChatMessageTemplateRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     *
     * @return bool True se autorizado.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
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
