<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para atualização de agente de IA.
 */
final class AiAgentUpdateRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('ai_agents', 'name')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->ignore($this->route('agent')),
            ],
            'type' => [
                'sometimes',
                'string',
                Rule::in(['sales', 'support', 'qualifier', 'general']),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $agent = $this->route('agent');
                    $agentId = $agent instanceof \Domain\Ai\Models\AiAgent ? $agent->id : $agent;
                    $isActive = $this->has('is_active') ? $this->boolean('is_active') : ($agent instanceof \Domain\Ai\Models\AiAgent ? $agent->is_active : true);

                    if ($value === 'general' && $isActive) {
                        $exists = \Domain\Ai\Models\AiAgent::query()
                            ->where('tenant_id', $this->user()->tenant_id)
                            ->where('type', 'general')
                            ->where('is_active', true)
                            ->where('id', '!=', $agentId)
                            ->exists();

                        if ($exists) {
                            $fail('Apenas um agente do tipo "general" ativo é permitido por empresa.');
                        }
                    }
                },
            ],
            'model_id' => ['sometimes', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'system_prompt' => ['nullable', 'string'],
            'max_tokens' => ['sometimes', 'integer', 'min:100', 'max:8192'],
            'temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'top_p' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['sometimes', 'boolean'],
            'parent_agent_id' => ['nullable', 'uuid'],
            'classifier_model' => ['nullable', 'string', 'max:50'],
            'token_budget_input' => ['sometimes', 'integer', 'min:0'],
            'token_budget_output' => ['sometimes', 'integer', 'min:0'],
            'fallback_message' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'voice_response_mode' => ['sometimes', 'string', 'in:text,audio,mixed'],
            'stt_model' => ['nullable', 'string', 'max:50'],
            'stt_language' => ['nullable', 'string', 'max:16'],
            'tts_model' => ['nullable', 'string', 'max:50'],
            'tts_voice' => ['nullable', 'string', 'max:50'],
            'tts_speed' => ['nullable', 'numeric', 'min:0.5', 'max:2'],
        ];
    }
}
