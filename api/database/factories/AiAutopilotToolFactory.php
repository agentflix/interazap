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
            'send_message',
            'read_ticket',
            'close_ticket',
            'search_knowledge',
            'create_note',
            'create_task',
            'update_lead_score',
            'transfer_to_human',
            'notify_seller',
            'create_contact',
            'update_contact',
            'search_contacts',
            'schedule_event',
            'list_products',
            'check_availability',
            'get_available_slots',
            'confirm_event_booking',
            'get_contact_info',
            'update_contact_tags',
            'move_pipeline',
            'create_company',
            'update_company',
            'create_negotiation',
            'close_negotiation',
            'get_negotiation_info',
            'add_product_to_negotiation',
            'qualify_lead',
            'list_funnel_steps',
            'create_proposal',
            'link_contact_to_company',
            'delegate_to_agent',
        ]);

        return [
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $name,
            'display_name' => str_replace('_', ' ', $name),
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
