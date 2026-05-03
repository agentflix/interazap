<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para criação de agente de IA.
 */
final class AiAgentStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
                'required',
                'string',
                'max:100',
                Rule::unique('ai_agents', 'name')->where('tenant_id', $this->user()->tenant_id),
            ],
            'type' => [
                'required',
                'string',
                Rule::in(['sales', 'support', 'qualifier', 'general']),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === 'general' && $this->input('is_active', true)) {
                        $exists = \Domain\Ai\Models\AiAgent::query()
                            ->where('tenant_id', $this->user()->tenant_id)
                            ->where('type', 'general')
                            ->where('is_active', true)
                            ->exists();

                        if ($exists) {
                            $fail('Apenas um agente do tipo "general" ativo é permitido por empresa.');
                        }
                    }
                },
            ],
            'model_id' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'system_prompt' => ['nullable', 'string'],
            'max_tokens' => ['required', 'integer', 'min:100', 'max:8192'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'top_p' => ['required', 'numeric', 'min:0', 'max:1'],
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'model_id' => $this->input('model_id', 'gpt-4o-mini'),
            'max_tokens' => $this->input('max_tokens', 2048),
            'temperature' => $this->input('temperature', 0.7),
            'top_p' => $this->input('top_p', 1.0),
            'is_active' => $this->input('is_active', true),
            'token_budget_input' => $this->input('token_budget_input', 0),
            'token_budget_output' => $this->input('token_budget_output', 0),
            'voice_response_mode' => $this->input('voice_response_mode', 'text'),
            'tts_speed' => $this->input('tts_speed', 1.0),
        ]);
    }
}
