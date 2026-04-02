<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketExtended;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatTicketExtended>
 */
class ChatTicketExtendedFactory extends Factory
{
    protected $model = ChatTicketExtended::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'ticket_id' => ChatTicket::factory(),
            'subject' => $this->faker->sentence(3),
            'profile_picture_url' => $this->faker->optional()->imageUrl(),
            'human_takeover_at' => null,
            'closed_by' => null,
            'close_reason' => null,
            'auto_close_queue_after_minutes' => 0,
            'auto_close_in_progress_after_minutes' => 0,
            'sla_first_response_due_at' => null,
            'sla_resolution_due_at' => null,
            'sla_first_response_breached' => false,
            'sla_resolution_breached' => false,
        ];
    }

    public function breached(): self
    {
        return $this->state(fn (): array => [
            'sla_first_response_breached' => true,
            'sla_resolution_breached' => true,
        ]);
    }

    public function forTicket(string $ticketId): self
    {
        return $this->state(fn (): array => ['ticket_id' => $ticketId]);
    }
}
