<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiAutopilotPlaybook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiAutopilotPlaybook>
 */
class AiAutopilotPlaybookFactory extends Factory
{
    protected $model = AiAutopilotPlaybook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => fn () => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first()->id ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->unique()->sentence(3),
            'description' => $this->faker->sentence(),
            'version' => 1,
            'steps' => [
                ['step' => 1, 'name' => 'Collect context'],
                ['step' => 2, 'name' => 'Run decision engine'],
                ['step' => 3, 'name' => 'Execute actions'],
            ],
            'metadata' => ['source' => 'factory'],
            'is_active' => true,
        ];
    }
}
