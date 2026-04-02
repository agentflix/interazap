<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatQuickAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatQuickAnswer>
 */
class ChatQuickAnswerFactory extends Factory
{
    protected $model = ChatQuickAnswer::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);
        $shortcut = '/'.$this->faker->unique()->word();

        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $name,
            'shortcut' => $shortcut,
            'content' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['greetings', 'sales', 'support']),
            'is_active' => true,
        ];
    }
}
