<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * Guardrail evaluation outcome.
 *
 * @readonly
 */
final readonly class GuardrailEvaluationResult
{
    /**
     * @param  array<int, array<string, mixed>>  $evaluations
     */
    public function __construct(
        public bool $blocked,
        public array $evaluations,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $evaluations
     */
    public static function passed(array $evaluations = []): self
    {
        return new self(blocked: false, evaluations: $evaluations);
    }

    /**
     * @param  array<int, array<string, mixed>>  $evaluations
     */
    public static function blocked(array $evaluations): self
    {
        return new self(blocked: true, evaluations: $evaluations);
    }
}
