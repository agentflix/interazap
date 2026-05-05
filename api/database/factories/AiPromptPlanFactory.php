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

    private function generatePlanPromptContent(): string
    {
        return <<<'PROMPT'
REGRAS DO PLANO:
- Respostas devem ser concisas e objetivas
- Limite de contexto por conversa conforme plano contratado
- Funcionalidades avançadas disponíveis conforme nível do plano
PROMPT;
    }
}
