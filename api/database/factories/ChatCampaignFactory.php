<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatCampaign>
 */
class ChatCampaignFactory extends Factory
{
    protected $model = ChatCampaign::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->words(3, true),
            'message' => $this->faker->sentence(),
            'status' => 'draft',
            'scheduled_at' => null,
            'metadata' => ['env' => 'test'],
        ];
    }
}
