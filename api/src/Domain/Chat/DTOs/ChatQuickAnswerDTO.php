<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Http\Request;

/**
 * DTO de Resposta Rápida.
 *
 * @readonly
 */
final readonly class ChatQuickAnswerDTO
{
    /**
     * @param  string  $name  Nome identificador da resposta.
     * @param  string  $content  Texto de resposta inserido no chat.
     * @param  string|null  $shortcut  Atalho opcional (ex.: /ola) para busca rápida.
     * @param  string|null  $category  Categoria para organização.
     * @param  bool  $isActive  Indica se a resposta está disponível.
     */
    public function __construct(
        public string $name,
        public string $content,
        public ?string $shortcut = null,
        public ?string $category = null,
        public bool $isActive = true,
    ) {}

    /**
     * Cria o DTO a partir de um Request HTTP.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            content: $request->string('content')->toString(),
            shortcut: $request->input('shortcut'),
            category: $request->input('category'),
            isActive: $request->boolean('is_active', true),
        );
    }

    /**
     * Cria o DTO a partir de um array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            content: (string) $data['content'],
            shortcut: $data['shortcut'] ?? null,
            category: $data['category'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'content' => $this->content,
            'shortcut' => $this->shortcut,
            'category' => $this->category,
            'is_active' => $this->isActive,
        ];
    }
}
