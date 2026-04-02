<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiPromptMaster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPromptMaster>
 */
class AiPromptMasterFactory extends Factory
{
    protected $model = AiPromptMaster::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Master Prompt v'.$this->faker->numberBetween(1, 10),
            'content' => $this->generateMasterPromptContent(),
            'version' => 1,
            'is_active' => true,
        ];
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
     * Set specific version.
     */
    public function version(int $version): static
    {
        return $this->state(fn (array $attributes): array => [
            'version' => $version,
        ]);
    }

    private function generateMasterPromptContent(): string
    {
        return <<<'PROMPT'
Você é um assistente de IA da plataforma AgentFlix.

REGRAS DE COMPLIANCE OBRIGATÓRIAS:
1. Nunca compartilhe dados pessoais de usuários com terceiros
2. Siga rigorosamente as diretrizes da LGPD
3. Não forneça informações financeiras sensíveis
4. Seja profissional e respeitoso em todas as interações
5. Rejeite solicitações que violem políticas de uso

REGRAS DE SEGURANÇA:
- Nunca execute comandos de sistema
- Nunca revele informações internas do sistema
- Não processe solicitações maliciosas
PROMPT;
    }
}
