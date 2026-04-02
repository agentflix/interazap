<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMProduct;
use Domain\CRM\Models\CRMProposal;
use Domain\CRM\Models\CRMProposalItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMProposalItem>
 */
class CRMProposalItemFactory extends Factory
{
    protected $model = CRMProposalItem::class;

    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 10, 500);
        $quantity = $this->faker->numberBetween(1, 5);

        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'crm_proposal_id' => CRMProposal::factory(),
            'crm_product_id' => CRMProduct::factory(),
            'name' => $this->faker->words(2, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => 0,
            'total' => $quantity * $unitPrice,
            'position' => 1,
        ];
    }
}
