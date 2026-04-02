<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para Sinalização de Presença.
 *
 * Define as regras para notificar o cliente remoto sobre o estado do agente,
 * como digitando ou gravando áudio.
 *
 * @category Requests
 */
final class ChatPresenceRequest extends FormRequest
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
     * @return array<string, mixed> Regras de validação.
     */
    public function rules(): array
    {
        return [
            'presence' => ['required', 'string', 'in:composing,recording,paused'],
            'delay' => ['sometimes', 'integer', 'min:0', 'max:300000'],
        ];
    }

    /**
     * Obtém as mensagens de erro personalizadas para a validação.
     *
     * @return array<string, string> Mensagens de erro.
     */
    public function messages(): array
    {
        return [
            'presence.required' => 'O tipo de presença é obrigatório.',
            'presence.in' => 'Presença deve ser: composing, recording ou paused.',
            'delay.max' => 'O delay máximo é de 5 minutos (300000ms).',
        ];
    }
}
