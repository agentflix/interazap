<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Platform\Models\PlatformUazapiInstance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for PlatformUazapiInstance model.
 *
 * @extends Factory<PlatformUazapiInstance>
 */
class PlatformUazapiInstanceFactory extends Factory
{
    /**
     * @var class-string<PlatformUazapiInstance>
     */
    protected $model = PlatformUazapiInstance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::orderedUuid()->toString(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->company(),
            'system_name' => $this->faker->slug(2),
            'token' => Str::random(64),
            'status' => 'connected',
            'webhook_url' => $this->faker->url(),
            'last_status_at' => now(),
        ];
    }

    /**
     * Set instance as connected.
     */
    public function connected(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'connected',
        ]);
    }

    /**
     * Set instance as disconnected.
     */
    public function disconnected(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'disconnected',
        ]);
    }
}
