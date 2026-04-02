<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationTask;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMNegotiationTask>
 */
class CRMNegotiationTaskFactory extends Factory
{
    protected $model = CRMNegotiationTask::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'crm_negotiation_id' => CRMNegotiation::factory(),
            'auth_user_id' => null,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'due_date' => now()->addDays(5),
            'status' => 'pending',
        ];
    }
}
