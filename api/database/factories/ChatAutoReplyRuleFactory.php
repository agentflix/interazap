<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatAutoReplyRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatAutoReplyRule>
 */
class ChatAutoReplyRuleFactory extends Factory
{
    protected $model = ChatAutoReplyRule::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->words(2, true),
            'trigger_text' => $this->faker->word(),
            'response_text' => $this->faker->sentence(),
            'is_active' => true,
            'is_welcome' => false,
            'cooldown_seconds' => $this->faker->numberBetween(0, 3600),
        ];
    }
}
