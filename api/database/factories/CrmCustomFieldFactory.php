<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMCustomField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMCustomField>
 */
class CRMCustomFieldFactory extends Factory
{
    protected $model = CRMCustomField::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->unique()->word(),
            'type' => 'text',
            'entity' => 'contact',
            'options' => null,
            'is_required' => false,
        ];
    }
}
