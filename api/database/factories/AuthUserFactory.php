<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Auth\Models\AuthUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuthUser>
 */
class AuthUserFactory extends Factory
{
    protected $model = AuthUser::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'password' => bcrypt('password'),
            'is_active' => true,
            'two_factor_enabled' => false,
            'preferences' => [],
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
