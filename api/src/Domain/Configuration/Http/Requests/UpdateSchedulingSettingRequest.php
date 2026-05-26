<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para atualização de configurações de agendamento.
 *
 * @category Requests
 */
class UpdateSchedulingSettingRequest extends FormRequest
{
    /** Verifica se o usuário está autorizado a realizar esta requisição. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retorna as regras de validação aplicadas à requisição.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_confirmation_advance_minutes' => ['sometimes', 'integer', 'min:15', 'max:4320'],
            'event_confirmation_enabled' => ['sometimes', 'boolean'],
            'event_confirmation_notify_ui' => ['sometimes', 'boolean'],
            'event_confirmation_notify_push' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Retorna as mensagens de validação personalizadas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event_confirmation_advance_minutes.min' => 'A antecedência mínima é 15 minutos.',
            'event_confirmation_advance_minutes.max' => 'A antecedência máxima é 4320 minutos (72 horas).',
            'event_confirmation_advance_minutes.integer' => 'O valor deve ser um número inteiro.',
            'event_confirmation_enabled.boolean' => 'O campo deve ser verdadeiro ou falso.',
            'event_confirmation_notify_ui.boolean' => 'O campo deve ser verdadeiro ou falso.',
            'event_confirmation_notify_push.boolean' => 'O campo deve ser verdadeiro ou falso.',
        ];
    }
}
