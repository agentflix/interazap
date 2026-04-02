<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNote;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMNote>
 */
class CRMNoteFactory extends Factory
{
    protected $model = CRMNote::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'entity_type' => CRMContact::class,
            'entity_id' => (string) Str::orderedUuid(),
            'auth_user_id' => AuthUser::factory(),
            'content' => $this->faker->sentence(),
        ];
    }
}
