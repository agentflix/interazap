<?php

declare(strict_types=1);

namespace Domain\Gateway\DTOs\AI;

use Domain\Gateway\Enums\AIFinishReason;

/**
 * DTO imutável que representa a resposta de uma conclusão (completion) recebida do gateway de IA.
 *
 * Contém conteúdo gerado, contagens de tokens, modelo utilizado e chamadas de ferramentas.
 */
final readonly class AICompletionResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $toolCalls  Chamadas de ferramentas retornadas pelo modelo
     */
    public function __construct(
        public string $content,
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
        public string $model,
        public ?AIFinishReason $finishReason,
        public array $toolCalls = [],
    ) {}

    /**
     * Cria uma instância a partir de um array, tipicamente vindo da resposta do gateway.
     *
     * @param  array<string, mixed>  $data  Dados brutos da resposta
     */
    public static function fromArray(array $data): self
    {
        return new self(
            content: $data['content'],
            promptTokens: $data['promptTokens'],
            completionTokens: $data['completionTokens'],
            totalTokens: $data['totalTokens'],
            model: $data['model'],
            finishReason: isset($data['finishReason'])
                ? AIFinishReason::tryFrom($data['finishReason'])
                : null,
            toolCalls: self::normalizeToolCalls($data),
        );
    }

    /** Verifica se a resposta contém chamadas de ferramentas. */
    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /**
     * Normaliza os dados de tool calls para formato unificado entre providers.
     *
     * OpenAI envia camelCase (functionName, toolCallId); outros providers
     * podem usar snake_case (function_name, tool_call_id).
     *
     * @param  array<string, mixed>  $data  Dados brutos da resposta
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeToolCalls(array $data): array
    {
        $toolCalls = $data['toolCalls'] ?? $data['tool_calls'] ?? [];

        return is_array($toolCalls) ? array_values($toolCalls) : [];
    }
}
