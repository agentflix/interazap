<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiAutopilotGuardrail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAutopilotGuardrail>
 */
class AiAutopilotGuardrailFactory extends Factory
{
    protected $model = AiAutopilotGuardrail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->unique()->words(3, true),
            'description' => $this->faker->sentence(),
            'rule_type' => $this->faker->randomElement(['LOG_ONLY', 'WARN', 'BLOCK']),
            'conditions' => [
                'operator' => 'contains',
                'value' => $this->faker->word(),
            ],
            'priority' => $this->faker->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
