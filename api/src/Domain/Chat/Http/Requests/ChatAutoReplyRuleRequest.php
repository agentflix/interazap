<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para Regras de Auto Reply.
 *
 * Define as regras de integridade para criação e atualização de regras
 * de automação, incluindo gatilhos, respostas e configurações de cooldown.
 *
 * @category Requests
 */
class ChatAutoReplyRuleRequest extends FormRequest
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

        if ($user->can('chat.auto_reply_rules.manage')) {
            return true;
        }

        if ($this->isMethod('POST')) {
            return $user->can('chat.auto_reply_rules.create');
        }

        return $user->can('chat.auto_reply_rules.update');
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
            'trigger_text' => ['required_without:patterns', 'string', 'max:500'],
            'patterns' => ['required_without:trigger_text', 'array', 'min:1'],
            'patterns.*' => ['string', 'max:500'],
            'response_text' => ['required_without:actions', 'string'],
            'actions' => ['required_without:response_text', 'array', 'min:1'],
            'actions.*.type' => ['required_with:actions', 'string', 'in:send_message,transfer_department,transfer_agent,close_ticket,add_tag,create_task'],
            'actions.*.message' => ['nullable', 'string'],
            'actions.*.message_type' => ['nullable', 'string'],
            'actions.*.department_id' => ['nullable', 'uuid'],
            'is_active' => ['boolean'],
            'is_welcome' => ['boolean'],
            'cooldown_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
