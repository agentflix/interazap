<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para Abertura de Ticket.
 *
 * Define as regras para criação manual de tickets de atendimento,
 * consolidando dados do canal, contato e informações de perfil.
 *
 * @category Requests
 */
class ChatTicketStoreRequest extends FormRequest
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

        return $user->can('chat.tickets.manage')
            || $user->can('chat.tickets.create');
    }

    /**
     * Define as regras de validação que se aplicam à requisição.
     *
     * @return array<string, string|array<int, string>> Regras de validação.
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string'],
            'contact_id' => ['nullable', 'uuid'],
            'instance_id' => ['nullable', 'uuid'],
            'remote_jid' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'push_name' => ['nullable', 'string', 'max:255'],
            'profile_picture_url' => ['nullable', 'string', 'max:1024'],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_e164' => ['nullable', 'string', 'max:50'],
        ];
    }
}
