<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para Envio/Registro de Mensagem.
 *
 * Define as regras para criação de mensagens em um ticket, suportando
 * conteúdos textuais e mídias, além de vincular opcionalmente contatos ou usuários.
 *
 * @category Requests
 */
class ChatMessageStoreRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     *
     * @return bool True se autorizado.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('chat.messages.manage')
            || $user->can('chat.messages.create');
    }

    /**
     * Define as regras de validação que se aplicam à requisição.
     *
     * @return array<string, string|array<int, string>> Regras de validação.
     */
    public function rules(): array
    {
        return [
            'content' => ['required_without:file_url', 'nullable', 'string'],
            'direction' => ['nullable', 'in:incoming,outgoing'],
            'type' => ['nullable', 'string'],
            'is_from_contact' => ['nullable', 'boolean'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'contact_id' => ['nullable', 'uuid'],
            'user_id' => ['nullable', 'uuid'],
            'metadata' => ['nullable', 'array'],
            'file_url' => ['nullable', 'string', 'max:2048'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:100'],
            'file_size' => ['nullable', 'integer'],
        ];
    }
}
