<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatMessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessageTemplate>
 */
class ChatMessageTemplateFactory extends Factory
{
    protected $model = ChatMessageTemplate::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->words(3, true),
            'shortcut' => '/'.$this->faker->unique()->lexify('tpl_????'),
            'content' => $this->faker->sentence(8),
            'category' => $this->faker->randomElement(['sales', 'support', 'billing', 'onboarding']),
            'is_active' => true,
        ];
    }
}
