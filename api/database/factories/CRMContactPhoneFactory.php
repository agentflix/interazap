<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMContactPhone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMContactPhone>
 */
class CRMContactPhoneFactory extends Factory
{
    protected $model = CRMContactPhone::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'crm_contact_id' => CRMContact::factory(),
            'label' => 'mobile',
            'phone_e164' => $this->faker->unique()->e164PhoneNumber(),
            'is_primary' => false,
            'valid_from' => now(),
            'valid_to' => null,
        ];
    }
}
