<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationProduct;
use Domain\CRM\Models\CRMProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMNegotiationProduct>
 */
class CRMNegotiationProductFactory extends Factory
{
    protected $model = CRMNegotiationProduct::class;

    public function definition(): array
    {
        $tenant = \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory();

        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant,
            'crm_negotiation_id' => CRMNegotiation::factory()->for($tenant, 'tenant'),
            'crm_product_id' => CRMProduct::factory()->for($tenant, 'tenant'),
            'name' => $this->faker->words(2, true),
            'quantity' => $this->faker->numberBetween(1, 5),
            'unit_price' => $this->faker->randomFloat(2, 10, 500),
            'total' => fn (array $attributes): int|float => $attributes['quantity'] * $attributes['unit_price'],
        ];
    }
}
