<?php

declare(strict_types=1);

namespace Domain\Gateway\DTOs\AI;

final readonly class AICompletionRequest
{
    /**
     * @param  array<array{role: string, content: string}>  $messages
     * @param  array<int, array<string, mixed>>|null  $tools
     */
    public function __construct(
        public array $messages,
        public ?string $model = null,
        public ?int $maxTokens = null,
        public ?float $temperature = null,
        public bool $stream = false,
        /** @var array<int, array<string, mixed>>|null */
        public ?array $tools = null,
    ) {}

    /**
     * Create a new AICompletionRequest with the given messages.
     *
     * @param  array<array{role: string, content: string}>  $messages
     */
    public static function create(array $messages): self
    {
        return new self(messages: $messages);
    }

    /**
     * Create a new instance with the specified model.
     */
    public function withModel(string $model): self
    {
        return new self(
            messages: $this->messages,
            model: $model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            stream: $this->stream,
            tools: $this->tools,
        );
    }

    /**
     * Create a new instance with the specified max tokens.
     */
    public function withMaxTokens(int $maxTokens): self
    {
        return new self(
            messages: $this->messages,
            model: $this->model,
            maxTokens: $maxTokens,
            temperature: $this->temperature,
            stream: $this->stream,
            tools: $this->tools,
        );
    }

    /**
     * Create a new instance with the specified temperature.
     */
    public function withTemperature(float $temperature): self
    {
        return new self(
            messages: $this->messages,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $temperature,
            stream: $this->stream,
            tools: $this->tools,
        );
    }

    /**
     * Create a new instance with streaming enabled or disabled.
     */
    public function withStream(bool $stream = true): self
    {
        return new self(
            messages: $this->messages,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            stream: $stream,
            tools: $this->tools,
        );
    }

    /**
     * Create a new instance with tool definitions.
     *
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function withTools(array $tools): self
    {
        return new self(
            messages: $this->messages,
            model: $this->model,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            stream: $this->stream,
            tools: $tools,
        );
    }

    /**
     * Convert the request to an array, filtering out null values.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'messages' => $this->messages,
            'model' => $this->model,
            'maxTokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'stream' => $this->stream,
            'tools' => $this->tools,
        ], fn ($v) => $v !== null);
    }
}
