<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiUsageLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
{
    protected $model = AiUsageLog::class;

    public function definition(): array
    {
        $inputTokens = $this->faker->numberBetween(100, 5000);
        $outputTokens = $this->faker->numberBetween(50, 2000);

        // Simulate pricing at ~$3/1M input, ~$15/1M output
        $inputCost = round(($inputTokens / 1_000_000) * 3.00, 6);
        $outputCost = round(($outputTokens / 1_000_000) * 15.00, 6);

        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'user_id' => null,
            'ai_model_pricing_id' => null,
            'model_name' => $this->faker->randomElement(['gpt-4o', 'gpt-4o-mini', 'claude-3-5-sonnet']),
            'provider' => $this->faker->randomElement(['openai', 'anthropic']),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
            'feature' => $this->faker->randomElement(['chat', 'automation', 'rag', 'summary']),
            'latency_ms' => $this->faker->numberBetween(200, 3000),
        ];
    }

    /**
     * Set a specific model.
     */
    public function forModel(string $modelName, string $provider = 'openai'): self
    {
        return $this->state([
            'model_name' => $modelName,
            'provider' => $provider,
        ]);
    }

    /**
     * Set a specific feature.
     */
    public function forFeature(string $feature): self
    {
        return $this->state(['feature' => $feature]);
    }

    /**
     * Create with specific token counts.
     */
    public function withTokens(int $input, int $output): self
    {
        $inputCost = round(($input / 1_000_000) * 3.00, 6);
        $outputCost = round(($output / 1_000_000) * 15.00, 6);

        return $this->state([
            'input_tokens' => $input,
            'output_tokens' => $output,
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
        ]);
    }

    /**
     * Create old log for LGPD testing.
     */
    public function old(int $daysAgo = 100): self
    {
        return $this->state([
            'created_at' => now()->subDays($daysAgo),
            'updated_at' => now()->subDays($daysAgo),
        ]);
    }
}
