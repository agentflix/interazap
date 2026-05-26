<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Http\Request;

/**
 * DTO que representa uma regra de proteção (guardrail) do Autopilot.
 *
 * Utilizado para definir barreiras de segurança que validam mensagens antes
 * e depois do processamento pela IA, prevenindo respostas inadequadas,
 * vazamento de dados ou comportamento fora do escopo definido.
 *
 * @readonly
 */
final readonly class AiAutopilotGuardrailDTO
{
    /**
     * @param  array<string, mixed>|null  $conditions
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public string $ruleType = 'LOG_ONLY',
        public ?array $conditions = null,
        public int $priority = 1,
        public bool $isActive = true,
    ) {}

    /**
     * Cria o DTO a partir de um request HTTP.
     *
     * @param  Request  $request  Requisição HTTP validada.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            description: $request->input('description'),
            ruleType: $request->string('rule_type', 'LOG_ONLY')->toString(),
            conditions: $request->input('conditions') ? (array) $request->input('conditions') : null,
            priority: (int) $request->input('priority', 1),
            isActive: (bool) $request->input('is_active', true),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: $data['description'] ?? null,
            ruleType: (string) ($data['rule_type'] ?? 'LOG_ONLY'),
            conditions: isset($data['conditions']) ? (array) $data['conditions'] : null,
            priority: (int) ($data['priority'] ?? 1),
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
            'description' => $this->description,
            'rule_type' => $this->ruleType,
            'conditions' => $this->conditions,
            'priority' => $this->priority,
            'is_active' => $this->isActive,
        ];
    }
}
