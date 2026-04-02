<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMNegotiation>
 */
class CRMNegotiationFactory extends Factory
{
    protected $model = CRMNegotiation::class;

    public function configure(): static
    {
        return $this
            ->afterMaking(function (CRMNegotiation $negotiation): void {
                $this->synchronizeFunnelWithStep($negotiation);
            })
            ->afterCreating(function (CRMNegotiation $negotiation): void {
                $this->synchronizeFunnelWithStep($negotiation, true);
            });
    }

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'crm_company_id' => null,
            'crm_contact_id' => fn (array $attributes): string => CRMContact::factory()
                ->create(['tenant_id' => $attributes['tenant_id']])
                ->id,
            'crm_negotiation_funnel_id' => fn (array $attributes): string => CRMNegotiationFunnel::factory()
                ->create(['tenant_id' => $attributes['tenant_id']])
                ->id,
            'crm_negotiation_funnel_step_id' => fn (array $attributes): string => CRMNegotiationFunnelStep::factory()
                ->create([
                    'tenant_id' => $attributes['tenant_id'],
                    'crm_negotiation_funnel_id' => $attributes['crm_negotiation_funnel_id'],
                ])
                ->id,
            'title' => $this->faker->sentence(3),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'status' => 'open',
            'lead_score' => $this->faker->numberBetween(0, 100),
        ];
    }

    private function synchronizeFunnelWithStep(CRMNegotiation $negotiation, bool $persist = false): void
    {
        $step = CRMNegotiationFunnelStep::query()->find($negotiation->crm_negotiation_funnel_step_id);
        if (! $step) {
            return;
        }

        $hasChanges = false;

        if ($negotiation->crm_negotiation_funnel_id !== $step->crm_negotiation_funnel_id) {
            $negotiation->crm_negotiation_funnel_id = $step->crm_negotiation_funnel_id;
            $hasChanges = true;
        }

        if ($negotiation->tenant_id !== $step->tenant_id) {
            $negotiation->tenant_id = $step->tenant_id;
            $hasChanges = true;
        }

        if ($persist && $hasChanges) {
            $negotiation->saveQuietly();
        }
    }
}
