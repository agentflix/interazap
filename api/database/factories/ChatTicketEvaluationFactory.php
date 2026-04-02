<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatTicketEvaluation>
 */
class ChatTicketEvaluationFactory extends Factory
{
    protected $model = ChatTicketEvaluation::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $this->faker->uuid(),
            'ticket_id' => ChatTicket::factory(),
            'token' => Str::random(32),
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->optional()->sentence(),
            'submitted_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function promoters(): self
    {
        return $this->state(fn (): array => ['rating' => $this->faker->numberBetween(4, 5)]);
    }

    public function passives(): self
    {
        return $this->state(fn (): array => ['rating' => 3]);
    }

    public function detractors(): self
    {
        return $this->state(fn (): array => ['rating' => $this->faker->numberBetween(1, 2)]);
    }

    public function forTicket(string $ticketId): self
    {
        return $this->state(fn (): array => ['ticket_id' => $ticketId]);
    }
}
