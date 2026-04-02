<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiKnowledgeDocument>
 */
class AiKnowledgeDocumentFactory extends Factory
{
    protected $model = AiKnowledgeDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileTypesWithExtensions = array_values(array_filter(
            AiDocumentType::cases(),
            static fn (AiDocumentType $type): bool => count($type->extensions()) > 0,
        ));

        $fileType = $this->faker->randomElement($fileTypesWithExtensions);
        $extension = $fileType->extensions()[0];
        $fileName = $this->faker->words(3, true).'.'.$extension;

        return [
            'tenant_id' => \Domain\Platform\Models\PlatformTenant::query()->inRandomOrder()->first() ?? \Domain\Platform\Models\PlatformTenant::factory(),
            'name' => $this->faker->sentence(3),
            'original_filename' => $fileName,
            'file_path' => 'knowledge/'.$this->faker->uuid().'/'.$fileName,
            'file_size_bytes' => $this->faker->numberBetween(1024, 1024 * 1024), // 1KB to 1MB
            'file_type' => $fileType,
            'version' => 1,
            'replaced_by' => null,
            'chunk_count' => 0,
            'embedding_status' => AiEmbeddingStatus::PENDING,
            'error_message' => null,
            'metadata' => null,
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
     * Set as TXT file.
     */
    public function txt(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_type' => AiDocumentType::TXT,
            'original_filename' => $this->faker->words(3, true).'.txt',
        ]);
    }

    /**
     * Set as CSV file.
     */
    public function csv(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_type' => AiDocumentType::CSV,
            'original_filename' => $this->faker->words(3, true).'.csv',
        ]);
    }

    /**
     * Set as Markdown file.
     */
    public function markdown(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_type' => AiDocumentType::MARKDOWN,
            'original_filename' => $this->faker->words(3, true).'.md',
        ]);
    }

    /**
     * Set as JSON file.
     */
    public function json(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_type' => AiDocumentType::JSON,
            'original_filename' => $this->faker->words(3, true).'.json',
        ]);
    }

    /**
     * Set as PDF file.
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_type' => AiDocumentType::PDF,
            'original_filename' => $this->faker->words(3, true).'.pdf',
        ]);
    }

    /**
     * Set as pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'embedding_status' => AiEmbeddingStatus::PENDING,
            'chunk_count' => 0,
        ]);
    }

    /**
     * Set as processing status.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'embedding_status' => AiEmbeddingStatus::PROCESSING,
            'chunk_count' => 0,
        ]);
    }

    /**
     * Set as ready status with chunks.
     */
    public function ready(int $chunkCount = 10): static
    {
        return $this->state(fn (array $attributes): array => [
            'embedding_status' => AiEmbeddingStatus::READY,
            'chunk_count' => $chunkCount,
        ]);
    }

    /**
     * Set as failed status.
     */
    public function failed(string $errorMessage = 'Processing failed'): static
    {
        return $this->state(fn (array $attributes): array => [
            'embedding_status' => AiEmbeddingStatus::FAILED,
            'error_message' => $errorMessage,
            'chunk_count' => 0,
        ]);
    }

    /**
     * Set as inactive (soft deleted).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific version.
     */
    public function version(int $version): static
    {
        return $this->state(fn (array $attributes): array => [
            'version' => $version,
        ]);
    }

    /**
     * Set replaced by another document.
     */
    public function replacedBy(AiKnowledgeDocument $document): static
    {
        return $this->state(fn (array $attributes): array => [
            'replaced_by' => $document->id,
            'is_active' => false,
        ]);
    }

    /**
     * Set with specific file size.
     */
    public function withFileSize(int $bytes): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_size_bytes' => $bytes,
        ]);
    }

    /**
     * Set with metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes): array => [
            'metadata' => $metadata,
        ]);
    }
}
