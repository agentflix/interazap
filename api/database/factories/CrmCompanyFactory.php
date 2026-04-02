<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\CRM\Models\CRMCompany;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMCompany>
 */
class CRMCompanyFactory extends Factory
{
    protected $model = CRMCompany::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->unique()->company(),
            'document' => $this->faker->numerify('##############'),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->e164PhoneNumber(),
            'is_active' => true,
        ];
    }
}
