<?php

declare(strict_types=1);

namespace Domain\Ai\Validators;

use Domain\Ai\DTOs\RegexValidationResult;

/**
 * Validador de Regex para Anti-Injection em prompts.
 *
 * Implementa os 7 padrões de detecção de tentativas de injeção de prompt
 * conforme especificado no PRD. A validação é síncrona e deve ser < 10ms.
 */
final class AiPromptRegexValidator
{
    /**
     * Lista de padrões de injeção bloqueados.
     *
     * @var array<string, string>
     */
    private const BLOCKED_PATTERNS = [
        'ignore_previous' => '/ignore\s+previous/i',
        'you_are_now' => '/you\s+are\s+now/i',
        'disregard_instructions' => '/disregard\s+(all\s+)?instructions/i',
        'system_override' => '/system\s+override/i',
        'developer_mode' => '/developer\s+mode/i',
        'forget_everything' => '/forget\s+everything/i',
        'new_persona' => '/new\s+persona/i',
    ];

    /**
     * Valida o conteúdo do prompt contra padrões de injeção.
     *
     * @param  string  $content  O conteúdo do prompt a ser validado
     * @return RegexValidationResult Resultado da validação
     */
    public function validate(string $content): RegexValidationResult
    {
        foreach (self::BLOCKED_PATTERNS as $patternName => $regex) {
            if (preg_match($regex, $content) === 1) {
                return RegexValidationResult::failed($patternName, $regex);
            }
        }

        return RegexValidationResult::passed();
    }

    /**
     * Retorna todos os padrões que combinam com o conteúdo.
     *
     * @param  string  $content  O conteúdo do prompt a ser validado
     * @return array<string, string> Mapa de padrão => regex dos matches
     */
    public function findAllMatches(string $content): array
    {
        $matches = [];

        foreach (self::BLOCKED_PATTERNS as $patternName => $regex) {
            if (preg_match($regex, $content) === 1) {
                $matches[$patternName] = $regex;
            }
        }

        return $matches;
    }

    /**
     * Retorna a lista de padrões bloqueados.
     *
     * @return array<string, string>
     */
    public function getPatterns(): array
    {
        return self::BLOCKED_PATTERNS;
    }

    /**
     * Retorna o número de padrões verificados.
     */
    public function getPatternCount(): int
    {
        return count(self::BLOCKED_PATTERNS);
    }
}
