<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Ai\Models;

use Domain\Ai\Models\AiAutopilotGuardrail;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAutopilotGuardrail>
 */
class AiAutopilotGuardrailFactory extends Factory
{
    protected $model = AiAutopilotGuardrail::class;

    public function definition(): array
    {
        return [
            'tenant_id' => PlatformTenant::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'rule_type' => $this->faker->randomElement(['LOG_ONLY', 'BLOCK', 'HITL']),
            'conditions' => [],
            'priority' => $this->faker->numberBetween(1, 100),
            'is_active' => true,
        ];
    }
}
