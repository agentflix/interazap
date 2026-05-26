<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO para requisições de completude de IA.
 *
 * @readonly
 */
final readonly class AiCompletionDTO
{
    /**
     * @param  string  $prompt  Prompt a completar.
     * @param  string|null  $model  Modelo a usar (usa padrão do provider se null).
     * @param  float  $temperature  Temperatura de amostragem (0.0 - 2.0).
     * @param  int  $maxTokens  Máximo de tokens a gerar.
     * @param  array<string, mixed>  $context  Dados de contexto adicionais.
     * @param  array<int, array<string, string>>  $messages  Mensagens para chat completion.
     * @param  string|null  $systemPrompt  System prompt para chat completion.
     */
    public function __construct(
        public string $prompt,
        public ?string $model = null,
        public float $temperature = 0.7,
        public int $maxTokens = 2048,
        public array $context = [],
        public array $messages = [],
        public ?string $systemPrompt = null,
    ) {}
}
