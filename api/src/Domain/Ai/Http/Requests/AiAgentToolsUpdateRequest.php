<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualização de ferramentas de agente de IA.
 */
final class AiAgentToolsUpdateRequest extends FormRequest
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
            'tool_names' => ['nullable', 'array'],
            'tool_names.*' => ['required', 'string'],
            'tool_ids' => ['nullable', 'array'],
            'tool_ids.*' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tool_names') || ! $this->has('tool_ids')) {
            return;
        }

        $this->merge([
            'tool_names' => $this->input('tool_ids', []),
        ]);
    }
}
