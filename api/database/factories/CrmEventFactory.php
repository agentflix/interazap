<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CRMEvent>
 */
class CRMEventFactory extends Factory
{
    protected $model = CRMEvent::class;

    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('+1 hour', '+2 days');

        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'auth_user_id' => null,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'location' => $this->faker->city(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+1 hour'),
            'is_all_day' => false,
            'status' => CRMEvent::STATUS_SCHEDULED,
            'type' => CRMEvent::TYPE_MEETING,
            'recurrence' => CRMEvent::RECURRENCE_NONE,
            'recurrence_ends_at' => null,
            'color' => '#3366ff',
        ];
    }

    public function withUser(AuthUser $user): self
    {
        return $this->state(fn (): array => [
            'tenant_id' => $user->tenant_id,
            'auth_user_id' => $user->id,
        ]);
    }
}
