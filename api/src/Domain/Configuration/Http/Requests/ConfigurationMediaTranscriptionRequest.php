<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualização das configurações de transcrição de mídia.
 *
 * @category Requests
 */
final class ConfigurationMediaTranscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'media_transcription_audio_enabled' => ['required', 'boolean'],
            'media_transcription_image_enabled' => ['required', 'boolean'],
            'media_transcription_video_enabled' => ['required', 'boolean'],
            'media_transcription_audio_max_minutes' => ['required', 'integer', 'min:1', 'max:25'],
            'media_transcription_image_max_per_message' => ['required', 'integer', 'min:1', 'max:10'],
            'media_transcription_video_max_seconds' => ['required', 'integer', 'min:5', 'max:120'],
        ];
    }

    /**
     * Mensagens de validação customizadas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'media_transcription_audio_max_minutes.max' => 'O limite máximo de áudio é 25 minutos.',
            'media_transcription_video_max_seconds.max' => 'O limite máximo de vídeo é 120 segundos.',
            'media_transcription_image_max_per_message.max' => 'O limite máximo de imagens por mensagem é 10.',
        ];
    }
}
