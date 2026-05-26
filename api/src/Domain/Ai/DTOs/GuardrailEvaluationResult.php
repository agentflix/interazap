<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO com o resultado de avaliação de guardrails do Autopilot.
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
     * Cria resultado indicando que nenhum guardrail bloqueou a execução.
     *
     * @param  array<int, array<string, mixed>>  $evaluations  Avaliações realizadas.
     */
    public static function passed(array $evaluations = []): self
    {
        return new self(blocked: false, evaluations: $evaluations);
    }

    /**
     * Cria resultado indicando que um guardrail bloqueou a execução.
     *
     * @param  array<int, array<string, mixed>>  $evaluations  Avaliações que resultaram no bloqueio.
     */
    public static function blocked(array $evaluations): self
    {
        return new self(blocked: true, evaluations: $evaluations);
    }
}
