<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiPromptPlan;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPromptPlan>
 */
class AiPromptPlanFactory extends Factory
{
    protected $model = AiPromptPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => PlatformPlan::factory(),
            'content' => $this->generatePlanPromptContent(),
            'mandatory_rules' => $this->generateMandatoryRules(),
            'token_limit_monthly' => $this->faker->randomElement([50000, 100000, 500000, 1000000, null]),
            'allow_overage' => $this->faker->boolean(30),
            'overage_price_per_1k' => $this->faker->randomFloat(6, 0.001, 0.01),
            'is_active' => true,
        ];
    }

    /**
     * Create for a specific plan.
     */
    public function forPlan(PlatformPlan $plan): static
    {
        return $this->state(fn (array $attributes): array => [
            'plan_id' => $plan->id,
        ]);
    }

    /**
     * Set as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * With unlimited tokens.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes): array => [
            'token_limit_monthly' => null,
            'allow_overage' => false,
        ]);
    }

    /**
     * With overage enabled.
     */
    public function withOverage(float $pricePerK = 0.005): static
    {
        return $this->state(fn (array $attributes): array => [
            'allow_overage' => true,
            'overage_price_per_1k' => $pricePerK,
        ]);
    }

    private function generatePlanPromptContent(): string
    {
        return <<<'PROMPT'
REGRAS DO PLANO:
- Respostas devem ser concisas e objetivas
- Limite de contexto por conversa conforme plano contratado
- Funcionalidades avançadas disponíveis conforme nível do plano
PROMPT;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generateMandatoryRules(): array
    {
        return [
            [
                'rule' => 'response_length',
                'description' => 'Respostas devem ter no máximo 500 palavras',
                'enforceable' => true,
            ],
            [
                'rule' => 'no_external_links',
                'description' => 'Não incluir links externos nas respostas',
                'enforceable' => true,
            ],
        ];
    }
}
