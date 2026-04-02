<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO for AI completion results.
 *
 * @readonly
 */
final readonly class AiCompletionResultDTO
{
    /**
     * @param  string  $content  The generated content.
     * @param  string  $model  The model used.
     * @param  int  $tokensUsed  Total tokens used (prompt + completion).
     * @param  int  $promptTokens  Tokens used in the prompt.
     * @param  int  $completionTokens  Tokens used in the completion.
     * @param  string  $finishReason  Reason for completion (stop, length, etc.).
     * @param  array<string, mixed>  $metadata  Additional metadata.
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
