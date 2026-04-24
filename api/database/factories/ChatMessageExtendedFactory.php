<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatMessageExtended;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessageExtended>
 */
class ChatMessageExtendedFactory extends Factory
{
    protected $model = ChatMessageExtended::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'message_id' => (string) Str::orderedUuid(),
            'file_url' => null,
            'file_name' => null,
            'mime_type' => null,
            'file_size' => null,
            'media_transcription' => null,
            'media_transcription_provider' => null,
            'media_transcription_status' => null,
            'media_transcription_tokens' => null,
            'media_transcription_cost' => null,
            'media_transcribed_at' => null,
            'reactions' => [],
            'is_edited' => false,
            'edited_at' => null,
            'edit_history' => [],
            'error_message' => null,
        ];
    }

    public function withFile(string $url, string $name, string $mime, int $size): static
    {
        return $this->state(fn (array $attrs): array => [
            'file_url' => $url,
            'file_name' => $name,
            'mime_type' => $mime,
            'file_size' => $size,
        ]);
    }
}
