<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) Str::orderedUuid(),
            'ticket_id' => ChatTicket::factory(),
            'user_id' => null,
            'contact_id' => null,
            'content' => $this->faker->sentence(),
            'type' => 'text',
            'direction' => 'outgoing',
            'is_from_contact' => false,
            'source' => 'agent',
            'status' => 'sent',
            'file_url' => null,
            'file_name' => null,
            'mime_type' => null,
            'file_size' => null,
            'external_id' => null,
            'metadata' => [],
            'error_message' => null,
            'sent_at' => now(),
            'delivered_at' => null,
            'read_at' => null,
            'reactions' => [],
            'is_edited' => false,
            'edited_at' => null,
            'edit_history' => [],
            'is_deleted' => false,
            'deleted_at' => null,
            'deleted_by' => null,
        ];
    }
}
