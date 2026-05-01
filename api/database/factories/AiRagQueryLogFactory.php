<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiRagQueryLog;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiRagQueryLog>
 */
class AiRagQueryLogFactory extends Factory
{
    protected $model = AiRagQueryLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => PlatformTenant::query()->inRandomOrder()->first() ?? PlatformTenant::factory(),
            'query_hash' => hash('sha256', $this->faker->sentence),
            'query_length' => $this->faker->numberBetween(10, 200),
            'mode' => $this->faker->randomElement(['vector', 'hybrid']),
            'results_count' => $this->faker->numberBetween(0, 10),
            'top_score' => $this->faker->randomFloat(4, 0, 1),
            'avg_score' => $this->faker->randomFloat(4, 0, 1),
            'latency_ms' => $this->faker->numberBetween(50, 2000),
            'has_results' => $this->faker->boolean(80),
            'created_at' => now(),
        ];
    }

    /**
     * Create for a specific tenant.
     */
    public function forTenant(PlatformTenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Set as vector mode.
     */
    public function vector(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mode' => 'vector',
        ]);
    }

    /**
     * Set as hybrid mode.
     */
    public function hybrid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'mode' => 'hybrid',
        ]);
    }

    /**
     * Set has_results to false.
     */
    public function noResults(): static
    {
        return $this->state(fn (array $attributes): array => [
            'has_results' => false,
            'results_count' => 0,
            'top_score' => null,
            'avg_score' => null,
        ]);
    }
}
