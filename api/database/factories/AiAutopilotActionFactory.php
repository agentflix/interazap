<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiAutopilotAction;
use Domain\Ai\Models\AiAutopilotRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAutopilotAction>
 */
class AiAutopilotActionFactory extends Factory
{
    protected $model = AiAutopilotAction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => AiAutopilotRun::factory(),
            'action_type' => $this->faker->randomElement(['tool_call', 'classification', 'message_send']),
            'input' => ['payload' => $this->faker->sentence()],
            'output' => ['result' => $this->faker->sentence()],
            'guardrail_result' => $this->faker->optional()->randomElement(['allowed', 'warned', 'blocked']),
            'order' => $this->faker->numberBetween(1, 6),
        ];
    }
}
