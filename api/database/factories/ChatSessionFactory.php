<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatSession;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatSession>
 */
class ChatSessionFactory extends Factory
{
    protected $model = ChatSession::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => ChatTicket::factory()->create()->tenant_id,
            'contact_id' => fn (array $attributes) => CRMContact::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'ticket_id' => fn (array $attributes) => ChatTicket::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'token' => (string) Str::uuid(),
            'client_info' => [
                'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari']),
                'os' => fake()->randomElement(['Windows', 'macOS', 'Linux']),
            ],
            'last_activity_at' => null,
        ];
    }

    public function forTicket(ChatTicket $ticket): self
    {
        return $this->state(fn (): array => [
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
        ]);
    }
}
