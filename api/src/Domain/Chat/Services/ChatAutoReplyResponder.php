<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Actions\SendChatMessageAction;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Jobs\ChatAutoReplyRespondJob;
use Domain\Chat\Models\ChatAutoReplyRule;
use Domain\Chat\Models\ChatTicket;

/**
 * Motor de Resposta para Auto Reply.
 *
 * Gerencia a execução de regras de auto-resposta, validando gatilhos textuais.
 *
 * @category Services
 */
final class ChatAutoReplyResponder
{
    /**
     * @param  SendChatMessageAction  $messageActions  Action de envio de mensagens.
     */
    public function __construct(
        private readonly SendChatMessageAction $messageActions,
    ) {}

    /**
     * Enqueue auto reply response for async processing.
     *
     * @param  string  $tenantId  Tenant identifier.
     * @param  string  $ticketId  Ticket UUID.
     * @param  string  $body  Incoming message body.
     * @param  bool  $isFirstInteraction  Whether this is the first ticket interaction.
     */
    public function dispatch(string $tenantId, string $ticketId, string $body, bool $isFirstInteraction = false): void
    {
        ChatAutoReplyRespondJob::dispatch($tenantId, $ticketId, $body, $isFirstInteraction)
            ->onQueue('auto-reply');
    }

    /**
     * Avalia uma mensagem recebida e dispara respostas se os critérios forem atendidos.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  UUID do ticket onde a mensagem foi recebida.
     * @param  string  $body  Conteúdo textual da mensagem para validação de gatilho.
     * @param  bool  $isFirstInteraction  Se é a primeira interação do ticket (ticket novo).
     */
    public function respond(string $tenantId, string $ticketId, string $body, bool $isFirstInteraction = false): void
    {
        if ($body === '') {
            return;
        }

        $ticket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->find($ticketId);

        if ($ticket !== null && $ticket->human_takeover_at !== null) {
            logger()->info('[ChatAutoReplyResponder] Automação ignorada: ticket sob atendimento humano', [
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketId,
                'human_takeover_at' => (string) $ticket->human_takeover_at,
            ]);

            return;
        }

        if ($isFirstInteraction) {
            $this->sendWelcomeMessage($tenantId, $ticketId);
        } else {
            $this->sendWelcomeMessageOnTrigger($tenantId, $ticketId, $body);
        }

        $rules = ChatAutoReplyRule::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_welcome', false)
            ->get();

        foreach ($rules as $rule) {
            if (! $this->matches($rule, $body)) {
                continue;
            }

            $this->createBotMessage($tenantId, $ticketId, (string) $rule->id, $rule->response_text);
        }
    }

    /**
     * Envia a mensagem de boas-vindas (menu) automaticamente na primeira interação.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  UUID do ticket.
     */
    private function sendWelcomeMessage(string $tenantId, string $ticketId): void
    {
        $welcomeRule = ChatAutoReplyRule::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_welcome', true)
            ->first();

        if (! $welcomeRule) {
            logger()->debug('[ChatAutoReplyResponder] Nenhuma regra de boas-vindas configurada', [
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketId,
            ]);

            return;
        }

        logger()->info('[ChatAutoReplyResponder] Enviando mensagem de boas-vindas', [
            'tenant_id' => $tenantId,
            'ticket_id' => $ticketId,
            'rule_id' => $welcomeRule->id,
        ]);

        $this->createBotMessage($tenantId, $ticketId, (string) $welcomeRule->id, $welcomeRule->response_text);
    }

    /**
     * Permite retrigger da regra de boas-vindas por palavra-chave em interações subsequentes.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  UUID do ticket.
     * @param  string  $body  Conteúdo da mensagem recebida.
     */
    private function sendWelcomeMessageOnTrigger(string $tenantId, string $ticketId, string $body): void
    {
        $welcomeRule = ChatAutoReplyRule::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_welcome', true)
            ->first();

        if (! $welcomeRule) {
            return;
        }

        if (! $this->matches($welcomeRule, $body)) {
            return;
        }

        logger()->info('[ChatAutoReplyResponder] Retrigger de boas-vindas por palavra-chave', [
            'tenant_id' => $tenantId,
            'ticket_id' => $ticketId,
            'rule_id' => $welcomeRule->id,
        ]);

        $this->createBotMessage($tenantId, $ticketId, (string) $welcomeRule->id, $welcomeRule->response_text);
    }

    /**
     * Cria uma mensagem de texto do BOT via SendChatMessageAction, tolerando
     * o guard de janela Meta (texto livre fora da janela é bloqueado — o
     * atendimento continua possível via template aprovado).
     */
    private function createBotMessage(
        string $tenantId,
        string $ticketId,
        string $ruleId,
        string $responseText,
    ): void {
        try {
            $this->messageActions->create($tenantId, new ChatMessageDTO(
                ticketId: $ticketId,
                content: $responseText,
                direction: 'outgoing',
                type: 'text',
                isFromContact: false,
                source: ChatMessageDTO::SOURCE_BOT
            ));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $reason = $exception->validator->errors()->first('message');

            // Só engole o bloqueio do GUARD DE JANELA (texto livre fora da
            // janela). Qualquer outra validação (conteúdo inválido, ticket
            // inexistente) é relançada — não pode ser silenciada.
            if ($reason === null || ! str_contains($reason, 'Janela 24h expirada')) {
                throw $exception;
            }

            logger()->info('[ChatAutoReplyResponder] Resposta do BOT bloqueada pelo guard de janela', [
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketId,
                'rule_id' => $ruleId,
                'reason' => $reason,
            ]);
        }
    }

    /**
     * Verifica se o corpo da mensagem corresponde ao gatilho da regra.
     *
     * @param  ChatAutoReplyRule  $rule  Regra a ser testada.
     * @param  string  $body  Texto da mensagem.
     * @return bool True se houver correspondência (insensível a maiúsculas/minúsculas).
     */
    private function matches(ChatAutoReplyRule $rule, string $body): bool
    {
        return stripos($body, $rule->trigger_text) !== false;
    }
}
