<?php

declare(strict_types=1);

namespace Domain\Gateway\DTOs\AI;

use Domain\Gateway\Enums\AIFinishReason;

final readonly class AICompletionResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $toolCalls
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
     * Create an AICompletionResponse from an array (typically from Gateway response).
     *
     * @param  array<string, mixed>  $data
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

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /**
     * Normalizes tool call data from different AI providers.
     *
     * OpenAI sends camelCase (functionName, toolCallId).
     * Some providers may send snake_case (function_name, tool_call_id).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeToolCalls(array $data): array
    {
        $toolCalls = $data['toolCalls'] ?? $data['tool_calls'] ?? [];

        return is_array($toolCalls) ? array_values($toolCalls) : [];
    }
}
