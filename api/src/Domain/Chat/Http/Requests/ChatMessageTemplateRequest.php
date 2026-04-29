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
        $isUpdate = $this->isMethod('PUT');
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'chat_instance_id' => ['nullable', 'string', 'uuid', 'exists:chat_instances,id'],
            'name' => ['required', 'string', 'max:255'],
            'content' => [$required, 'string'],
            'language' => [$required, 'string', 'max:10'],
            'category' => ['nullable', 'string', 'max:255'],
            'components' => ['nullable', 'array'],
            'components.*.type' => ['required_with:components', 'string', 'in:HEADER,BODY,FOOTER,BUTTONS'],
            'components.*.text' => ['nullable', 'string'],
            'shortcut' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'provider' => ['nullable', 'string', 'in:local,meta'],
        ];
    }
}
