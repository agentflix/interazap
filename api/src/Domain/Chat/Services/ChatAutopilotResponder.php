<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Ai\Events\AiRunRequested;

/**
 * Responder assíncrono para respostas do Autopilot.
 *
 * Resolve o playbook automaticamente a partir dos playbooks ativos do tenant.
 * Não requer configuração no nível da instância.
 *
 * @category Services
 */
final class ChatAutopilotResponder
{
    /**
     * Despacha job assíncrono para executar o Autopilot e responder.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket.
     * @param  string  $body  Conteúdo da mensagem recebida.
     * @param  array<string, mixed>  $context  Dados de contexto (instance_id, message_id, etc.).
     */
    public function respond(string $tenantId, string $ticketId, string $body, array $context = []): void
    {
        AiRunRequested::dispatch($tenantId, $ticketId, $body, $context);
    }
}
