<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMNegotiationFunnelStep>
 */
class CRMNegotiationFunnelStepFactory extends Factory
{
    protected $model = CRMNegotiationFunnelStep::class;

    public function definition(): array
    {
        static $order = 1;

        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'crm_negotiation_funnel_id' => CRMNegotiationFunnel::factory(),
            'name' => $this->faker->word(),
            'color' => $this->faker->hexColor(),
            'is_active' => true,
            'order' => $order++,
        ];
    }
}
