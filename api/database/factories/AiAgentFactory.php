<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAgent>
 */
class AiAgentFactory extends Factory
{
    protected $model = AiAgent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'type' => 'general',
            'is_active' => true,
            'max_tokens' => 4096,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'system_prompt' => $this->faker->paragraph(),
            'fallback_message' => 'Não consegui processar sua solicitação.',
            'token_budget_input' => 8192,
            'token_budget_output' => 4096,
        ];
    }
}
