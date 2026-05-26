<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criação e atualização de regras de delegação de agente de IA.
 */
final class AiAgentDelegationStoreRequest extends FormRequest
{
    /** Autorização delegada para o controller via policy. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'target_agent_id' => ['required', 'uuid'],
            'max_depth' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
