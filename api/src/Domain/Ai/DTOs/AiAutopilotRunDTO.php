<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Http\Request;

/**
 * DTO que representa a execução de um playbook do Autopilot.
 *
 * Utilizado ao disparar manualmente ou via trigger a execução de um playbook,
 * carregando o contexto necessário (ticket, conversa, dados do cliente)
 * para que o agente processe as regras definidas.
 *
 * @readonly
 */
final readonly class AiAutopilotRunDTO
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        public string $playbookId,
        public ?array $context = null,
    ) {}

    /**
     * Cria o DTO a partir de um request HTTP e ID do playbook da rota.
     *
     * @param  Request  $request  Requisição HTTP validada.
     * @param  string  $playbookId  UUID do playbook informado na URL.
     */
    public static function fromRequest(Request $request, string $playbookId): self
    {
        return self::fromArray([
            'playbook_id' => $playbookId,
            'context' => $request->input('context'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            playbookId: (string) ($data['playbook_id'] ?? ''),
            context: isset($data['context']) && is_array($data['context']) ? $data['context'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'playbook_id' => $this->playbookId,
            'context' => $this->context,
        ];
    }
}
