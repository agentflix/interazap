<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMCustomField;
use Domain\CRM\Models\CRMCustomFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMCustomFieldValue>
 */
class CRMCustomFieldValueFactory extends Factory
{
    protected $model = CRMCustomFieldValue::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'crm_custom_field_id' => CRMCustomField::factory(),
            'entity_type' => CRMContact::class,
            'entity_id' => (string) Str::orderedUuid(),
            'value' => $this->faker->word(),
        ];
    }
}
