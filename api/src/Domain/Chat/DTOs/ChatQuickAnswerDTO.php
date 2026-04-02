<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for Quick Answers.
 *
 * @readonly
 */
final readonly class ChatQuickAnswerDTO
{
    /**
     * @param  string  $name  Answer identifier name.
     * @param  string  $content  Response text inserted in chat.
     * @param  string|null  $shortcut  Optional shortcut (e.g. /hello) for quick search.
     * @param  string|null  $category  Category for organization.
     * @param  bool  $isActive  Whether the answer is available.
     */
    public function __construct(
        public string $name,
        public string $content,
        public ?string $shortcut = null,
        public ?string $category = null,
        public bool $isActive = true,
    ) {}

    /**
     * Create DTO from request.
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
     * Create DTO from array.
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
