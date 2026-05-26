<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de serialização das configurações de transcrição de mídia.
 */
final class ConfigurationMediaTranscriptionResource extends JsonResource
{
    /**
     * Transforma o recurso em um array para serialização JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'media_transcription_audio_enabled' => (bool) $this->resource->media_transcription_audio_enabled,
            'media_transcription_image_enabled' => (bool) $this->resource->media_transcription_image_enabled,
            'media_transcription_video_enabled' => (bool) $this->resource->media_transcription_video_enabled,
            'media_transcription_audio_max_minutes' => (int) $this->resource->media_transcription_audio_max_minutes,
            'media_transcription_image_max_per_message' => (int) $this->resource->media_transcription_image_max_per_message,
            'media_transcription_video_max_seconds' => (int) $this->resource->media_transcription_video_max_seconds,
        ];
    }
}
