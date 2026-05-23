<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para Filas de Roteamento de Atendimentos.
 *
 * Define as regras de integridade para criação, atualização e gestão de agentes
 * em filas de distribuição automática de tickets (por canal ou global).
 *
 * @category Requests
 */
final class ChatRoutingQueueRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     *
     * A autorização granular é delegada ao controller via $this->authorize().
     *
     * @return bool True se autorizado.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define as regras de validação que se aplicam à requisição.
     *
     * @return array<string, mixed> Regras de validação.
     */
    public function rules(): array
    {
        $isStoreQueue = $this->isMethod('POST') && ! $this->hasAny(['user_id', 'agents', 'skill']);
        $isStoreAgent = $this->has('user_id') && ! $this->hasAny(['agents', 'skill']);
        $isReorder = $this->has('agents');
        $isStoreSkill = $this->has('skill');

        return [
            'name' => [$isStoreQueue ? 'required' : 'sometimes', 'string', 'max:100'],
            'strategy' => [$isStoreQueue ? 'required' : 'sometimes', 'string', 'in:round_robin,least_busy,skill_based'],
            'is_enabled' => ['sometimes', 'boolean'],
            'max_open_tickets_per_agent' => ['nullable', 'integer', 'min:1'],
            'user_id' => [$isStoreAgent ? 'required' : 'sometimes', 'uuid'],
            'agents' => [$isReorder ? 'required' : 'sometimes', 'array'],
            'agents.*.user_id' => ['required_with:agents', 'uuid'],
            'agents.*.position' => ['required_with:agents', 'integer', 'min:0'],
            'skill' => [$isStoreSkill ? 'required' : 'sometimes', 'string', 'max:100'],
        ];
    }
}
