<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Ai\Models;

use Domain\Ai\Models\AiAutopilotApproval;
use Domain\Ai\Models\AiAutopilotRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiAutopilotApproval>
 */
class AiAutopilotApprovalFactory extends Factory
{
    protected $model = AiAutopilotApproval::class;

    public function definition(): array
    {
        return [
            'run_id' => AiAutopilotRun::factory(),
            'status' => $this->faker->randomElement(['PENDING', 'APPROVED', 'REJECTED']),
            'requested_action' => ['action' => 'test_action', 'params' => ['key' => 'value']],
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'rejected_reason' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'APPROVED',
            'approved_by' => (string) Str::orderedUuid(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'REJECTED',
            'rejected_at' => now(),
            'rejected_reason' => $this->faker->sentence(),
        ]);
    }
}
