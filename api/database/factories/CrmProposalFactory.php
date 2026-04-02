<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMProposal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMProposal>
 */
class CRMProposalFactory extends Factory
{
    protected $model = CRMProposal::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'crm_negotiation_id' => CRMNegotiation::factory(),
            'title' => $this->faker->sentence(3),
            'number' => $this->faker->numberBetween(1000, 9999),
            'total' => $this->faker->randomFloat(2, 100, 1000),
            'status' => 'draft',
            'valid_until' => null,
            'notes' => null,
            'accepted_at' => null,
            'rejected_at' => null,
        ];
    }
}
