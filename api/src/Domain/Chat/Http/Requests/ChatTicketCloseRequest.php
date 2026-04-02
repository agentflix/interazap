<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Domain\Chat\Models\ChatTicket;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para encerramento de ticket.
 *
 * @category Requests
 */
final class ChatTicketCloseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user('sanctum') ?? $this->user();

        if ($user === null) {
            return false;
        }

        // Verify ticket belongs to user's tenant
        $ticket = ChatTicket::query()
            ->where('id', $this->route('id'))
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (! $ticket) {
            return false;
        }

        return $user->can('update', $ticket);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'mode' => ['nullable', 'string', 'in:normal,forced'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.max' => 'O motivo do encerramento deve ter no máximo 500 caracteres.',
            'mode.in' => 'O modo de encerramento deve ser "normal" ou "forced".',
        ];
    }
}
