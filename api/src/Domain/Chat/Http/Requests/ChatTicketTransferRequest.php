<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para Transferência de Ticket.
 *
 * Define as regras para mover um ticket de um agente para outro,
 * exigindo o ID do usuário de destino e uma justificativa obrigatória.
 *
 * @category Requests
 */
class ChatTicketTransferRequest extends FormRequest
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
        $tenantId = (string) ($this->user()?->tenant_id ?? '');

        return [
            'to_user_id' => [
                'required',
                'uuid',
                Rule::exists(AuthUser::class, 'id')->where(
                    static fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'reason' => ['required', 'string', 'regex:/\\S/'],
        ];
    }
}
