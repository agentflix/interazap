<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMProduct>
 */
class CRMProductFactory extends Factory
{
    protected $model = CRMProduct::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'code' => strtoupper($this->faker->bothify('PRD-###??')),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['product', 'service']),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'cost' => $this->faker->randomFloat(2, 5, 300),
            'unit' => $this->faker->randomElement(['un', 'kg', 'h']),
            'stock_quantity' => $this->faker->numberBetween(0, 100),
            'min_stock' => $this->faker->numberBetween(0, 20),
            'is_active' => true,
            'is_featured' => $this->faker->boolean(20),
            'track_stock' => $this->faker->boolean(50),
            'stock' => $this->faker->numberBetween(0, 100),
        ];
    }
}
