<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para Campanhas de Chat.
 *
 * Define as regras de integridade para criação e atualização de campanhas,
 * validando campos como nome, status e agendamento.
 *
 * @category Requests
 */
class ChatCampaignRequest extends FormRequest
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

        if ($user->can('chat.campaigns.manage')) {
            return true;
        }

        if ($this->isMethod('POST')) {
            return $user->can('chat.campaigns.create');
        }

        return $user->can('chat.campaigns.update');
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
            'status' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
            'filter_criteria' => ['nullable', 'array'],
            'instance_id' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
