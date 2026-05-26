<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Illuminate\Http\Request;

/**
 * DTO de Regra de Auto Resposta.
 *
 * @readonly
 */
final readonly class ChatAutoReplyRuleDTO
{
    /**
     * @param  string  $name  Nome identificador da regra.
     * @param  string  $triggerText  Palavra-chave ou texto que dispara a regra.
     * @param  string  $responseText  Texto de resposta a ser enviado.
     * @param  bool  $isActive  Indica se a regra está ativa.
     * @param  bool  $isWelcome  Indica se esta é a regra de boas-vindas.
     * @param  int  $cooldownSeconds  Intervalo em segundos antes de permitir novo disparo para o mesmo ticket.
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
     * Cria o DTO a partir de um Request HTTP.
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
