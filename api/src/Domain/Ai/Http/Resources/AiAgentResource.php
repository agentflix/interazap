<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource para serialização de agente de IA.
 */
final class AiAgentResource extends BaseJsonResource
{
    /**
     * Transforma o recurso em array.
     *
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'model_id' => $this->model_id,
            'system_prompt' => $this->system_prompt,
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
            'top_p' => $this->top_p,
            'is_active' => $this->is_active,
            'parent_agent_id' => $this->parent_agent_id,
            'classifier_model' => $this->classifier_model,
            'token_budget_input' => $this->token_budget_input,
            'token_budget_output' => $this->token_budget_output,
            'fallback_message' => $this->fallback_message,
            'metadata' => $this->metadata,
            'voice_response_mode' => $this->voice_response_mode,
            'stt_model' => $this->stt_model,
            'stt_language' => $this->stt_language,
            'tts_model' => $this->tts_model,
            'tts_voice' => $this->tts_voice,
            'tts_speed' => $this->tts_speed,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
