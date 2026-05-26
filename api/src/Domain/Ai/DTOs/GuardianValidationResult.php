<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO para resultado de validação do Guardian LLM.
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
     * Cria um resultado indicando que o conteúdo é seguro.
     */
    public static function safe(): self
    {
        return new self(safe: true);
    }

    /**
     * Cria um resultado indicando que o conteúdo é inseguro.
     *
     * @param  string  $reason  Motivo da classificação como inseguro.
     * @param  string|null  $category  Categoria da ameaça detectada.
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
    public function isSafe(): bool
    {
        return $this->safe;
    }

    /**
     * Verifica se o conteúdo é inseguro.
     */
    public function isUnsafe(): bool
    {
        return ! $this->safe;
    }
}
