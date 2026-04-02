<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatTransmissionList;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatTransmissionList>
 */
class ChatTransmissionListFactory extends Factory
{
    protected $model = ChatTransmissionList::class;

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
