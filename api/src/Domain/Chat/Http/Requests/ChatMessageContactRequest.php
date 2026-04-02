<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Domain\Chat\Models\ChatTicket;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para envio de contato (vCard).
 *
 * @category Requests
 */
final class ChatMessageContactRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     *
     * Verifica existência de sessão, tenant válido, permissão de criação
     * de mensagens e que o ticket pertence ao tenant do usuário.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || (string) $user->tenant_id === '') {
            return false;
        }

        if (! $user->can('chat.messages.create')) {
            return false;
        }

        // Verify ticket belongs to user's tenant
        $ticket = ChatTicket::query()
            ->where('id', $this->route('ticketId'))
            ->where('tenant_id', $user->tenant_id)
            ->first();

        return $ticket !== null;
    }

    /**
     * Regras de validação.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact' => ['required', 'array'],
            'contact.fullName' => ['required', 'string', 'max:255'],
            'contact.phoneNumber' => ['required', 'string', 'max:50'],
            'contact.organization' => ['nullable', 'string', 'max:255'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.url' => ['nullable', 'url', 'max:500'],
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
            'contact.required' => 'Os dados do contato são obrigatórios.',
            'contact.fullName.required' => 'O nome completo é obrigatório.',
            'contact.phoneNumber.required' => 'O telefone é obrigatório.',
        ];
    }
}
