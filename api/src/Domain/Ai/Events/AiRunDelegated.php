<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento emitido após a criação do run filho resultante de uma delegação.
 *
 * Consumido pelo AiRunDelegatedStickyAgentListener para persistir o agente
 * alvo como sticky agent no ticket, de modo que mensagens futuras sejam
 * roteadas diretamente ao especialista sem passar pelo roteador principal.
 */
final class AiRunDelegated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $tenantId,
        public string $parentRunId,
        public string $childRunId,
        public string $sourceAgentId,
        public string $targetAgentId,
        public array $payload = [],
    ) {}
}
