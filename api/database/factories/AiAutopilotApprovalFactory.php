<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiAutopilotApproval;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Auth\Models\AuthUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAutopilotApproval>
 */
class AiAutopilotApprovalFactory extends Factory
{
    protected $model = AiAutopilotApproval::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => AiAutopilotRun::factory(),
            'tenant_id' => fn (array $attributes) => AiAutopilotRun::query()->find($attributes['run_id'])?->tenant_id
                ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'requested_action' => [
                'type' => 'tool_call',
                'tool' => 'TransferToHumanTool',
            ],
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'rejected_reason' => null,
        ];
    }

    /**
     * Define approved status.
     */
    public function approved(?AuthUser $user = null): self
    {
        return $this->state([
            'status' => 'approved',
            'approved_by' => $user?->id,
            'approved_at' => now(),
            'rejected_at' => null,
            'rejected_reason' => null,
        ]);
    }

    /**
     * Define rejected status.
     */
    public function rejected(string $reason = 'Business policy violation'): self
    {
        return $this->state([
            'status' => 'rejected',
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => now(),
            'rejected_reason' => $reason,
        ]);
    }
}
