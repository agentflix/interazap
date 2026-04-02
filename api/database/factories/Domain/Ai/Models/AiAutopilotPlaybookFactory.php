<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Ai\Models;

use Domain\Ai\Models\AiAutopilotPlaybook;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiAutopilotPlaybookFactory extends Factory
{
    protected $model = AiAutopilotPlaybook::class;

    public function definition(): array
    {
        return [
            'tenant_id' => PlatformTenant::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'version' => 1,
            'steps' => [['type' => 'action', 'name' => 'step1']],
            'metadata' => ['key' => 'value'],
            'is_active' => true,
        ];
    }
}
