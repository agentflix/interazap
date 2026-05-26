<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Ai\Events\AiRunRequested;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Services\PlatformPlanEnforcementService;

/**
 * Roteador de mensagens inbound recebidas via webhook.
 *
 * O roteamento é determinado pelo plano contratado do tenant:
 * - Plano com ai_enabled=true → fluxo de Autopilot com IA
 * - Plano sem IA ou sem plano → fluxo de Auto-Reply (baseado em regras)
 *
 * O campo mode da instância NÃO é utilizado nas decisões de roteamento.
 *
 * @category Services
 */
final class ChatWebhookRouter
{
    public function __construct(
        private readonly ChatAutoReplyResponder $autoReplyResponder,
        private readonly PlatformPlanEnforcementService $planEnforcement,
    ) {}

    /**
     * Roteia mensagens inbound para o fluxo de automação correto.
     *
     * Mensagens com takeover humano ativo são ignoradas. Mensagens vazias
     * também são descartadas sem processamento.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  ChatTicket  $ticket  Ticket relacionado à mensagem.
     * @param  string  $body  Conteúdo da mensagem recebida.
     * @param  array<string, mixed>  $context  Dados de contexto (instance_id, message_id, message_type, is_first_interaction).
     */
    public function routeInbound(string $tenantId, ChatTicket $ticket, string $body, array $context = []): void
    {
        if (trim($body) === '') {
            return;
        }

        $ticket->loadMissing('extended');

        if ($ticket->human_takeover_at) {
            logger()->info('[ChatWebhookRouter] Auto-resposta ignorada: atendimento humano em controle', [
                'ticket_id' => (string) $ticket->id,
                'instance_id' => (string) ($context['instance_id'] ?? ''),
            ]);

            return;
        }

        $isFirstInteraction = (bool) ($context['is_first_interaction'] ?? false);
        $aiEnabled = $this->planEnforcement->isAiEnabled($tenantId);

        logger()->info('[ChatWebhookRouter] Roteamento por plano', [
            'ai_enabled' => $aiEnabled,
            'ticket_id' => (string) $ticket->id,
            'instance_id' => (string) ($context['instance_id'] ?? ''),
            'is_first_interaction' => $isFirstInteraction,
        ]);

        if ($aiEnabled) {
            $this->handleAiFlow($tenantId, $ticket, $body, $context);

            return;
        }

        $this->autoReplyResponder->dispatch($tenantId, (string) $ticket->id, $body, $isFirstInteraction);
    }

    /**
     * Processa o fluxo de Autopilot com IA.
     *
     * Dispara o evento AiRunRequested para processamento assíncrono pelo módulo de IA.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  ChatTicket  $ticket  Ticket relacionado à mensagem.
     * @param  string  $body  Conteúdo da mensagem recebida.
     * @param  array<string, mixed>  $context  Dados de contexto adicionais.
     */
    private function handleAiFlow(string $tenantId, ChatTicket $ticket, string $body, array $context): void
    {
        AiRunRequested::dispatch(
            $tenantId,
            (string) $ticket->id,
            $body,
            $context,
        );
    }
}
