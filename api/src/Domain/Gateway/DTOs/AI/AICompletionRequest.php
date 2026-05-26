<?php

declare(strict_types=1);

namespace Domain\Gateway\DTOs\AI;

/**
 * DTO imutável que representa uma requisição de conclusão (completion) para um modelo de IA.
 *
 * Expõe construtores fluentes (withModel, withMaxTokens, etc.) para compor
 * a requisição sem mutar o objeto original.
 */
final readonly class AICompletionRequest
{
    /**
     * @param  array<array{role: string, content: string}>  $messages  Histórico de mensagens do chat
     * @param  array<int, array<string, mixed>>|null  $tools  Definições de ferramentas (tool calling)
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
     * Cria uma nova requisição com as mensagens informadas.
     *
     * @param  array<array{role: string, content: string}>  $messages  Histórico de mensagens do chat
     */
    public static function create(array $messages): self
    {
        return new self(messages: $messages);
    }

    /**
     * Retorna nova instância com o modelo especificado.
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
     * Retorna nova instância com o limite máximo de tokens especificado.
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
     * Retorna nova instância com a temperatura especificada.
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
     * Retorna nova instância com streaming habilitado ou desabilitado.
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
     * Retorna nova instância com as definições de ferramentas (tool calling).
     *
     * @param  array<int, array<string, mixed>>  $tools  Definições de ferramentas no formato da API
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
     * Converte a requisição em array, removendo campos nulos.
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
