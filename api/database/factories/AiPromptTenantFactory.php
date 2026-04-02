<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Enums\AiPromptValidationStatus;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPromptTenant>
 */
class AiPromptTenantFactory extends Factory
{
    protected $model = AiPromptTenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'segment_id' => AiPromptSegment::factory(),
            'content' => $this->generateTenantPromptContent(),
            'previous_content' => null,
            'version' => 1,
            'validation_status' => AiPromptValidationStatus::PENDING,
            'validated_hash' => null,
            'validated_at' => null,
            'is_active' => true,
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
     * Create with a specific segment.
     */
    public function withSegment(AiPromptSegment $segment): static
    {
        return $this->state(fn (array $attributes): array => [
            'segment_id' => $segment->id,
        ]);
    }

    /**
     * Set as approved with hash.
     */
    public function approved(): static
    {
        $content = $this->generateTenantPromptContent();

        return $this->state(fn (array $attributes): array => [
            'content' => $attributes['content'] ?? $content,
            'validation_status' => AiPromptValidationStatus::APPROVED,
            'validated_hash' => hash('sha256', $attributes['content'] ?? $content),
            'validated_at' => now(),
        ]);
    }

    /**
     * Set as pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'validation_status' => AiPromptValidationStatus::PENDING,
            'validated_hash' => null,
            'validated_at' => null,
        ]);
    }

    /**
     * Set as quarantined.
     */
    public function quarantined(): static
    {
        return $this->state(fn (array $attributes): array => [
            'validation_status' => AiPromptValidationStatus::QUARANTINE,
            'validated_hash' => null,
            'validated_at' => null,
        ]);
    }

    /**
     * Set as rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'validation_status' => AiPromptValidationStatus::REJECTED,
            'validated_hash' => null,
            'validated_at' => null,
        ]);
    }

    /**
     * With previous content for rollback testing.
     */
    public function withPreviousContent(?string $previousContent = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'previous_content' => $previousContent ?? 'Conteúdo anterior: '.$this->faker->sentence(),
            'version' => 2,
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

    private function generateTenantPromptContent(): string
    {
        $personas = [
            'Você é a assistente virtual da nossa empresa. Seja sempre cordial e ajude os clientes com suas dúvidas.',
            'Olá! Sou o assistente virtual. Estou aqui para ajudar você com informações sobre nossos produtos e serviços.',
            'Bem-vindo ao nosso atendimento! Como posso ajudá-lo hoje?',
        ];

        return $this->faker->randomElement($personas);
    }
}
