<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiModelPricing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiModelPricing>
 */
class AiModelPricingFactory extends Factory
{
    protected $model = AiModelPricing::class;

    public function definition(): array
    {
        $models = [
            ['provider' => 'openai', 'model' => 'gpt-4o', 'input' => 2.50, 'output' => 10.00],
            ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'input' => 0.15, 'output' => 0.60],
            ['provider' => 'openai', 'model' => 'gpt-4-turbo', 'input' => 10.00, 'output' => 30.00],
            ['provider' => 'anthropic', 'model' => 'claude-3-5-sonnet', 'input' => 3.00, 'output' => 15.00],
            ['provider' => 'anthropic', 'model' => 'claude-3-haiku', 'input' => 0.25, 'output' => 1.25],
            ['provider' => 'google', 'model' => 'gemini-1.5-pro', 'input' => 1.25, 'output' => 5.00],
        ];

        $selected = $this->faker->randomElement($models);

        return [
            'id' => (string) Str::orderedUuid(),
            'provider' => $selected['provider'],
            'model_name' => $selected['model'].'-'.$this->faker->unique()->randomNumber(4),
            'display_name' => null,
            'input_cost_per_1m' => $selected['input'],
            'output_cost_per_1m' => $selected['output'],
            'max_context_tokens' => 128000,
            'max_output_tokens' => 4096,
            'is_active' => true,
        ];
    }

    /**
     * Model ativo.
     */
    public function active(): self
    {
        return $this->state(['is_active' => true]);
    }

    /**
     * Model inativo.
     */
    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * OpenAI GPT-4o.
     */
    public function gpt4o(): self
    {
        return $this->state([
            'provider' => 'openai',
            'model_name' => 'gpt-4o',
            'display_name' => 'GPT-4o',
            'input_cost_per_1m' => 2.50,
            'output_cost_per_1m' => 10.00,
            'max_context_tokens' => 128000,
            'max_output_tokens' => 16384,
        ]);
    }

    /**
     * OpenAI GPT-4o Mini.
     */
    public function gpt4oMini(): self
    {
        return $this->state([
            'provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'display_name' => 'GPT-4o Mini',
            'input_cost_per_1m' => 0.15,
            'output_cost_per_1m' => 0.60,
            'max_context_tokens' => 128000,
            'max_output_tokens' => 16384,
        ]);
    }

    /**
     * Anthropic Claude 3.5 Sonnet.
     */
    public function claude35Sonnet(): self
    {
        return $this->state([
            'provider' => 'anthropic',
            'model_name' => 'claude-3-5-sonnet',
            'display_name' => 'Claude 3.5 Sonnet',
            'input_cost_per_1m' => 3.00,
            'output_cost_per_1m' => 15.00,
            'max_context_tokens' => 200000,
            'max_output_tokens' => 8192,
        ]);
    }
}
