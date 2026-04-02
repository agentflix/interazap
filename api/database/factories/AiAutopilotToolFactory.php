<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiAutopilotTool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAutopilotTool>
 */
class AiAutopilotToolFactory extends Factory
{
    protected $model = AiAutopilotTool::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'SearchKnowledgeTool',
            'SendMessageTool',
            'ReadTicketTool',
            'CloseTicketTool',
        ]);

        return [
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $name,
            'display_name' => str_replace('Tool', '', $name),
            'description' => $this->faker->sentence(),
            'parameters_schema' => [
                'type' => 'object',
                'properties' => [
                    'input' => ['type' => 'string'],
                ],
            ],
            'is_system' => true,
            'is_active' => true,
        ];
    }
}
