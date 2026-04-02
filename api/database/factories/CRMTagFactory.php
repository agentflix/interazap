<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMTag>
 */
class CRMTagFactory extends Factory
{
    protected $model = CRMTag::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'color' => $this->faker->hexColor(),
        ];
    }
}
