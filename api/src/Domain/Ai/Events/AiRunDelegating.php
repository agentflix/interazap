<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento emitido imediatamente antes de um run pai delegar a execução a outro agente.
 *
 * Permite registrar a intenção de delegação antes da criação do run filho,
 * útil para telemetria e auditoria do fluxo multi-agente.
 */
final class AiRunDelegating
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $tenantId,
        public string $parentRunId,
        public string $sourceAgentId,
        public string $targetAgentId,
        public array $payload = [],
    ) {}
}
