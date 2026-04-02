<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for Chatbot Rule.
 *
 * @readonly
 */
final readonly class ChatChatbotRuleDTO
{
    /**
     * @param  string  $name  Rule identifier name.
     * @param  string  $triggerText  Keyword or text that triggers the rule.
     * @param  string  $responseText  Response text to send.
     * @param  bool  $isActive  Whether the rule is enabled.
     * @param  bool  $isWelcome  Whether this is the welcome rule.
     * @param  int  $cooldownSeconds  Cooldown before allowing new trigger for same ticket.
     */
    public function __construct(
        public string $name,
        public string $triggerText,
        public string $responseText,
        public bool $isActive = true,
        public bool $isWelcome = false,
        public int $cooldownSeconds = 0,
    ) {}

    /**
     * Create DTO from request.
     */
    public static function fromRequest(Request $request): self
    {
        $triggerText = $request->filled('trigger_text')
            ? $request->string('trigger_text')->toString()
            : (string) data_get($request->input('patterns', []), '0', '');

        $responseText = $request->filled('response_text')
            ? $request->string('response_text')->toString()
            : (string) data_get($request->input('actions', []), '0.message', '');

        return self::fromArray([
            'name' => $request->input('name'),
            'trigger_text' => $triggerText,
            'response_text' => $responseText,
            'is_active' => $request->input('is_active'),
            'is_welcome' => $request->input('is_welcome'),
            'cooldown_seconds' => $request->input('cooldown_seconds'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            triggerText: (string) ($data['trigger_text'] ?? ''),
            responseText: (string) ($data['response_text'] ?? ''),
            isActive: (bool) ($data['is_active'] ?? true),
            isWelcome: (bool) ($data['is_welcome'] ?? false),
            cooldownSeconds: (int) ($data['cooldown_seconds'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'trigger_text' => $this->triggerText,
            'response_text' => $this->responseText,
            'is_active' => $this->isActive,
            'is_welcome' => $this->isWelcome,
            'cooldown_seconds' => $this->cooldownSeconds,
        ];
    }
}
