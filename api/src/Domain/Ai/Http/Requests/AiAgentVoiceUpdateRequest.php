<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualização da configuração de voz de agente de IA.
 */
final class AiAgentVoiceUpdateRequest extends FormRequest
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
            'voice_response_mode' => ['required', 'string', 'in:text,audio,mixed'],
            'stt_model' => ['nullable', 'string', 'max:50'],
            'stt_language' => ['nullable', 'string', 'max:16'],
            'tts_model' => ['nullable', 'string', 'max:50'],
            'tts_voice' => ['nullable', 'string', 'max:50'],
            'tts_speed' => ['nullable', 'numeric', 'min:0.5', 'max:2'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $voiceResponseMode = (string) $this->input('voice_response_mode', '');

        $normalizedMode = match ($voiceResponseMode) {
            'text_only' => 'text',
            'audio_only' => 'audio',
            'both' => 'mixed',
            default => $voiceResponseMode,
        };

        if ($normalizedMode !== '') {
            $this->merge([
                'voice_response_mode' => $normalizedMode,
            ]);
        }
    }
}
