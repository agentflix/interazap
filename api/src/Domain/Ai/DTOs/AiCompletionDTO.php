<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO for AI completion requests.
 *
 * @readonly
 */
final readonly class AiCompletionDTO
{
    /**
     * @param  string  $prompt  The prompt to complete.
     * @param  string|null  $model  The model to use (provider default if null).
     * @param  float  $temperature  The sampling temperature (0.0 - 2.0).
     * @param  int  $maxTokens  Maximum tokens to generate.
     * @param  array<string, mixed>  $context  Additional context data.
     * @param  array<int, array<string, string>>  $messages  Chat messages for chat completion.
     * @param  string|null  $systemPrompt  System prompt for chat completion.
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
