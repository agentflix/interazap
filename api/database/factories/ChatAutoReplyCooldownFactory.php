<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatAutoReplyCooldown;
use Domain\Chat\Models\ChatAutoReplyRule;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatAutoReplyCooldown>
 */
class ChatAutoReplyCooldownFactory extends Factory
{
    protected $model = ChatAutoReplyCooldown::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'ticket_id' => ChatTicket::factory(),
            'rule_id' => ChatAutoReplyRule::factory(),
            'cooldown_until' => now()->addMinutes($this->faker->numberBetween(10, 120)),
        ];
    }
}
