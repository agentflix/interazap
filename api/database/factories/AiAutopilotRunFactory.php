<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiAutopilotRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiAutopilotRun>
 */
class AiAutopilotRunFactory extends Factory
{
    protected $model = AiAutopilotRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-30 days', '-1 day');

        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => fn () => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first()->id ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'playbook_id' => function (array $attributes) {
                return \Domain\Ai\Models\AiAutopilotPlaybook::query()
                    ->where('tenant_id', $attributes['tenant_id'])
                    ->inRandomOrder()
                    ->first()->id ?? \Domain\Ai\Models\AiAutopilotPlaybook::factory()->create(['tenant_id' => $attributes['tenant_id']])->id;
            },
            'status' => $this->faker->randomElement(['running', 'completed', 'failed', 'cancelled']),
            'playbook_version' => 1,
            'input_context' => ['source' => 'factory'],
            'output' => ['message' => $this->faker->sentence()],
            'started_at' => $startedAt,
            'completed_at' => null,
        ];
    }
}
