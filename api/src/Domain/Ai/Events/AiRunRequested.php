<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando uma mensagem de entrada solicita processamento pelo Autopilot.
 *
 * Emitido pelo domínio Chat (ex.: ChatWebhookRouter e WebChatMessageController)
 * e interceptado pelo AiGateKeeperListener, que valida as condições antes de
 * publicar o AutopilotTriggerFired para o pipeline de IA.
 */
final class AiRunRequested
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $ticketId  UUID do ticket de origem.
     * @param  string  $body  Corpo da mensagem recebida.
     * @param  array<string, mixed>  $context  Contexto adicional (message_id, instance_id, etc.).
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly string $body,
        public readonly array $context = [],
    ) {}
}
