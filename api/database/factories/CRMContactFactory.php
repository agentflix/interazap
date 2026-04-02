<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMContact>
 */
class CRMContactFactory extends Factory
{
    protected $model = CRMContact::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'crm_company_id' => null,
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->e164PhoneNumber(),
            'whatsapp' => $this->faker->e164PhoneNumber(),
            'position' => $this->faker->jobTitle(),
            'is_active' => true,
        ];
    }

    public function withCompany(): self
    {
        return $this->state(fn (): array => ['crm_company_id' => CRMCompany::factory()]);
    }
}
