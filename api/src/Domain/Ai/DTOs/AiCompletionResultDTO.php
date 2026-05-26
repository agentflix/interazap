<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO para resultados de completude de IA.
 *
 * @readonly
 */
final readonly class AiCompletionResultDTO
{
    /**
     * @param  string  $content  Conteúdo gerado pelo modelo.
     * @param  string  $model  Modelo utilizado.
     * @param  int  $tokensUsed  Total de tokens utilizados (prompt + completude).
     * @param  int  $promptTokens  Tokens utilizados no prompt.
     * @param  int  $completionTokens  Tokens utilizados na completude.
     * @param  string  $finishReason  Motivo do encerramento (stop, length, etc.).
     * @param  array<string, mixed>  $metadata  Metadados adicionais.
     */
    public function __construct(
        public string $content,
        public string $model,
        public int $tokensUsed = 0,
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public string $finishReason = 'stop',
        public array $metadata = [],
    ) {}
}
