<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO para resultado de validação regex anti-injeção de prompt.
 *
 * Encapsula o resultado da primeira camada de proteção (síncrona) antes
 * do Guardian LLM (assíncrono). Construído via named constructors passed/failed.
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
     * Cria um resultado indicando que a validação passou sem problemas.
     */
    public static function passed(): self
    {
        return new self(passed: true);
    }

    /**
     * Cria um resultado indicando que um padrão proibido foi encontrado.
     *
     * @param  string  $patternName  Nome do padrão que casou.
     * @param  string  $regex  Expressão regular que casou.
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
    public function isValid(): bool
    {
        return $this->passed;
    }

    /**
     * Verifica se a validação falhou (padrão proibido detectado).
     */
    public function hasFailed(): bool
    {
        return ! $this->passed;
    }

    /**
     * Retorna mensagem de erro formatada ou null se a validação passou.
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
