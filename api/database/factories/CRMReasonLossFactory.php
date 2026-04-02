<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMReasonLoss;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMReasonLoss>
 */
class CRMReasonLossFactory extends Factory
{
    protected $model = CRMReasonLoss::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => 'Reason '.$this->faker->unique()->word(),
        ];
    }
}
