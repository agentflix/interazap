<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMNegotiationFunnel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMNegotiationFunnel>
 */
class CRMNegotiationFunnelFactory extends Factory
{
    protected $model = CRMNegotiationFunnel::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            // Prefix + unique words to respect UNIQUE (tenant_id, name)
            'name' => 'Padrão '.fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}
