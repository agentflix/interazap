<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Ai\Models;

use Domain\Ai\Models\AiAutopilotPlaybook;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAutopilotRun>
 */
class AiAutopilotRunFactory extends Factory
{
    protected $model = AiAutopilotRun::class;

    public function definition(): array
    {
        return [
            'tenant_id' => PlatformTenant::factory(),
            'playbook_id' => AiAutopilotPlaybook::factory(),
            'status' => $this->faker->randomElement(['RUNNING', 'COMPLETED', 'FAILED']),
            'playbook_version' => 1,
            'input_context' => ['key' => 'value'],
            'output' => [],
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
