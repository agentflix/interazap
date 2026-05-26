<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criação e atualização de gatilho de agente de IA.
 */
final class AiAgentTriggerStoreRequest extends FormRequest
{
    /** Verifica se o usuário possui permissão para gerenciar autopilots. */
    public function authorize(): bool
    {
        return $this->user()->can('ai.autopilots.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:50'],
            'config' => ['nullable', 'array'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ];
    }
}
