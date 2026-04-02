<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para Rotação de Atendentes.
 *
 * Define as regras para inclusão e atualização de usuários na fila de rotação,
 * validando o peso de distribuição e o status de ativação.
 *
 * @category Requests
 */
class ChatUserRotationRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     *
     * @return bool True se autorizado.
     */
    public function authorize(): bool
    {
        return $this->user()->can('chat.users.manage');
    }

    /**
     * Define as regras de validação que se aplicam à requisição.
     *
     * @return array<string, string|array<int, string>> Regras de validação.
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid'],
            'weight' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ];
    }
}
