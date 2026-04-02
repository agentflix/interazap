<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Models\AiPromptMaster;
use Domain\Ai\Models\AiPromptSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPromptSegment>
 */
class AiPromptSegmentFactory extends Factory
{
    protected $model = AiPromptSegment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $segments = ['RETAIL', 'CLINIC', 'REAL_ESTATE', 'EDUCATION', 'SERVICES', 'TECH'];

        return [
            'master_id' => null,
            'code' => $this->faker->unique()->randomElement($segments).'_'.$this->faker->unique()->numberBetween(1, 999),
            'name' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'content' => $this->generateSegmentPromptContent(),
            'is_active' => true,
        ];
    }

    /**
     * Create with associated master.
     */
    public function withMaster(?AiPromptMaster $master = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'master_id' => $master instanceof \Domain\Ai\Models\AiPromptMaster ? $master->id : AiPromptMaster::factory(),
        ]);
    }

    /**
     * Create the GENERAL segment.
     */
    public function general(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'GENERAL',
            'name' => 'Geral',
            'description' => 'Segmento padrão para atendimento geral.',
            'content' => 'Você é um assistente de atendimento ao cliente profissional e prestativo. Seja cordial, objetivo e sempre busque resolver as dúvidas do cliente de forma eficiente.',
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

    private function generateSegmentPromptContent(): string
    {
        $templates = [
            'Você é um especialista em atendimento para o setor de varejo. Ajude os clientes com dúvidas sobre produtos, preços e disponibilidade.',
            'Você é um assistente de clínica médica. Ajude pacientes a agendar consultas e tire dúvidas sobre procedimentos.',
            'Você é um corretor de imóveis virtual. Ajude clientes a encontrar o imóvel ideal baseado em suas necessidades.',
            'Você é um consultor educacional. Auxilie estudantes e pais com informações sobre cursos e matrículas.',
        ];

        return $this->faker->randomElement($templates);
    }
}
