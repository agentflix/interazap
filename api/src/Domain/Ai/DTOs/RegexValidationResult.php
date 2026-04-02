<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO for regex validation result (anti-injection).
 *
 * @readonly
 */
final readonly class RegexValidationResult
{
    private function __construct(
        public bool $passed,
        public ?string $matchedPattern = null,
        public ?string $matchedRegex = null,
    ) {}

    /**
     * Cria um resultado de validação bem-sucedida.
     */
    /**
     * Create passed result.
     */
    public static function passed(): self
    {
        return new self(passed: true);
    }

    /**
     * Cria um resultado de validação falha.
     */
    /**
     * Create failed result.
     */
    public static function failed(string $patternName, string $regex): self
    {
        return new self(
            passed: false,
            matchedPattern: $patternName,
            matchedRegex: $regex,
        );
    }

    /**
     * Verifica se a validação passou.
     */
    /**
     * Check if validation passed.
     */
    public function isValid(): bool
    {
        return $this->passed;
    }

    /**
     * Verifica se a validação falhou.
     */
    /**
     * Check if validation failed.
     */
    public function hasFailed(): bool
    {
        return ! $this->passed;
    }

    /**
     * Retorna mensagem de erro formatada.
     */
    /**
     * Get formatted error message.
     */
    public function getErrorMessage(): ?string
    {
        if ($this->passed) {
            return null;
        }

        return sprintf(
            'Prompt injection detected: pattern "%s" matched.',
            $this->matchedPattern
        );
    }
}
