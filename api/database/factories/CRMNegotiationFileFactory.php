<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMNegotiationFile>
 */
class CRMNegotiationFileFactory extends Factory
{
    protected $model = CRMNegotiationFile::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'crm_negotiation_id' => CRMNegotiation::factory(),
            'auth_user_id' => AuthUser::factory(),
            'name' => $this->faker->word().'.pdf',
            'path' => 'crm/files/'.$this->faker->uuid().'.pdf',
            'size' => $this->faker->numberBetween(1000, 5000),
            'mime_type' => 'application/pdf',
        ];
    }
}
