<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para dados de agente de IA.
 */
final readonly class AiAgentDTO
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $modelId,
        public ?string $systemPrompt,
        public int $maxTokens,
        public float $temperature,
        public float $topP,
        public bool $isActive,
        public ?string $parentAgentId = null,
        public ?string $classifierModel = null,
        public int $tokenBudgetInput = 0,
        public int $tokenBudgetOutput = 0,
        public ?string $fallbackMessage = null,
        public ?array $metadata = null,
        public string $voiceResponseMode = 'text',
        public ?string $sttModel = null,
        public ?string $sttLanguage = null,
        public ?string $ttsModel = null,
        public ?string $ttsVoice = null,
        public float $ttsSpeed = 1.0,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $voiceResponseMode = self::normalizeVoiceResponseMode($request->input('voice_response_mode'));

        return new self(
            name: $request->string('name')->toString(),
            type: $request->string('type')->toString(),
            modelId: $request->string('model_id')->toString(),
            systemPrompt: $request->input('system_prompt'),
            maxTokens: (int) $request->integer('max_tokens'),
            temperature: (float) $request->input('temperature'),
            topP: (float) $request->input('top_p'),
            isActive: (bool) $request->boolean('is_active'),
            parentAgentId: $request->input('parent_agent_id'),
            classifierModel: $request->input('classifier_model'),
            tokenBudgetInput: (int) $request->integer('token_budget_input', 0),
            tokenBudgetOutput: (int) $request->integer('token_budget_output', 0),
            fallbackMessage: $request->input('fallback_message'),
            metadata: is_array($request->input('metadata')) ? $request->input('metadata') : null,
            voiceResponseMode: $voiceResponseMode,
            sttModel: $request->input('stt_model'),
            sttLanguage: $request->input('stt_language'),
            ttsModel: $request->input('tts_model'),
            ttsVoice: $request->input('tts_voice'),
            ttsSpeed: (float) $request->input('tts_speed', 1.0),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $voiceResponseMode = self::normalizeVoiceResponseMode($data['voice_response_mode'] ?? null);

        return new self(
            name: (string) $data['name'],
            type: (string) $data['type'],
            modelId: (string) $data['model_id'],
            systemPrompt: isset($data['system_prompt']) ? (string) $data['system_prompt'] : null,
            maxTokens: (int) $data['max_tokens'],
            temperature: (float) $data['temperature'],
            topP: (float) $data['top_p'],
            isActive: (bool) $data['is_active'],
            parentAgentId: isset($data['parent_agent_id']) ? (string) $data['parent_agent_id'] : null,
            classifierModel: isset($data['classifier_model']) ? (string) $data['classifier_model'] : null,
            tokenBudgetInput: isset($data['token_budget_input']) ? (int) $data['token_budget_input'] : 0,
            tokenBudgetOutput: isset($data['token_budget_output']) ? (int) $data['token_budget_output'] : 0,
            fallbackMessage: isset($data['fallback_message']) ? (string) $data['fallback_message'] : null,
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null,
            voiceResponseMode: $voiceResponseMode,
            sttModel: isset($data['stt_model']) ? (string) $data['stt_model'] : null,
            sttLanguage: isset($data['stt_language']) ? (string) $data['stt_language'] : null,
            ttsModel: isset($data['tts_model']) ? (string) $data['tts_model'] : null,
            ttsVoice: isset($data['tts_voice']) ? (string) $data['tts_voice'] : null,
            ttsSpeed: isset($data['tts_speed']) ? (float) $data['tts_speed'] : 1.0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'model_id' => $this->modelId,
            'system_prompt' => $this->systemPrompt,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'top_p' => $this->topP,
            'is_active' => $this->isActive,
            'parent_agent_id' => $this->parentAgentId,
            'classifier_model' => $this->classifierModel,
            'token_budget_input' => $this->tokenBudgetInput,
            'token_budget_output' => $this->tokenBudgetOutput,
            'fallback_message' => $this->fallbackMessage,
            'metadata' => $this->metadata,
            'voice_response_mode' => $this->voiceResponseMode,
            'stt_model' => $this->sttModel,
            'stt_language' => $this->sttLanguage,
            'tts_model' => $this->ttsModel,
            'tts_voice' => $this->ttsVoice,
            'tts_speed' => $this->ttsSpeed,
        ];
    }

    private static function normalizeVoiceResponseMode(mixed $value): string
    {
        $mode = trim((string) $value);

        return match ($mode) {
            'text_only' => 'text',
            'audio_only' => 'audio',
            'both' => 'mixed',
            'audio', 'mixed' => $mode,
            default => 'text',
        };
    }
}
