<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO for LLM Guardian validation result.
 *
 * @readonly
 */
final readonly class GuardianValidationResult
{
    private function __construct(
        public bool $safe,
        public ?string $reason = null,
        public ?string $category = null,
    ) {}

    /**
     * Cria um resultado seguro.
     */
    /**
     * Create safe result.
     */
    public static function safe(): self
    {
        return new self(safe: true);
    }

    /**
     * Cria um resultado inseguro.
     */
    /**
     * Create unsafe result.
     */
    public static function unsafe(string $reason, ?string $category = null): self
    {
        return new self(
            safe: false,
            reason: $reason,
            category: $category,
        );
    }

    /**
     * Verifica se o conteúdo é seguro.
     */
    /**
     * Check if content is safe.
     */
    public function isSafe(): bool
    {
        return $this->safe;
    }

    /**
     * Verifica se o conteúdo é inseguro.
     */
    /**
     * Check if content is unsafe.
     */
    public function isUnsafe(): bool
    {
        return ! $this->safe;
    }
}
