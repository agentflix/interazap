<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Actions\SendChatMessageAction;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Jobs\ChatChatbotRespondJob;
use Domain\Chat\Models\ChatChatbotCooldown;
use Domain\Chat\Models\ChatChatbotRule;

/**
 * Motor de Resposta para Chatbot.
 *
 * Gerencia a execução de regras de auto-resposta, validando gatilhos textuais
 * e respeitando o período de cooldown (intervalo entre respostas)
 * para garantir uma experiência de usuário não-repetitiva.
 *
 * @category Services
 */
final class ChatChatbotResponder
{
    /**
     * @param  SendChatMessageAction  $messageActions  Action de envio de mensagens.
     */
    public function __construct(
        private readonly SendChatMessageAction $messageActions,
    ) {}

    /**
     * Enqueue chatbot response for async processing.
     *
     * @param  string  $tenantId  Tenant identifier.
     * @param  string  $ticketId  Ticket UUID.
     * @param  string  $body  Incoming message body.
     * @param  bool  $isFirstInteraction  Whether this is the first ticket interaction.
     */
    public function dispatch(string $tenantId, string $ticketId, string $body, bool $isFirstInteraction = false): void
    {
        ChatChatbotRespondJob::dispatch($tenantId, $ticketId, $body, $isFirstInteraction)
            ->onQueue('chatbot');
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

        // Se for primeira interação, enviar regra de boas-vindas automaticamente
        if ($isFirstInteraction) {
            $this->sendWelcomeMessage($tenantId, $ticketId);
        }

        $rules = ChatChatbotRule::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_welcome', false) // Ignorar regra de boas-vindas no fluxo normal
            ->get();

        foreach ($rules as $rule) {
            if (! $this->matches($rule, $body)) {
                continue;
            }

            if ($this->inCooldown($tenantId, $ticketId, $rule->id)) {
                continue;
            }

            $this->messageActions->create($tenantId, new ChatMessageDTO(
                ticketId: $ticketId,
                content: $rule->response_text,
                direction: 'outgoing',
                type: 'text',
                isFromContact: false,
                source: ChatMessageDTO::SOURCE_BOT
            ));

            $this->setCooldown($tenantId, $ticketId, $rule->id, (int) $rule->cooldown_seconds);
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
        $welcomeRule = ChatChatbotRule::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_welcome', true)
            ->first();

        if (! $welcomeRule) {
            logger()->debug('[ChatChatbotResponder] Nenhuma regra de boas-vindas configurada', [
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketId,
            ]);

            return;
        }

        // Verificar cooldown para evitar spam em reconexões
        if ($this->inCooldown($tenantId, $ticketId, $welcomeRule->id)) {
            return;
        }

        logger()->info('[ChatChatbotResponder] Enviando mensagem de boas-vindas', [
            'tenant_id' => $tenantId,
            'ticket_id' => $ticketId,
            'rule_id' => $welcomeRule->id,
        ]);

        $this->messageActions->create($tenantId, new ChatMessageDTO(
            ticketId: $ticketId,
            content: $welcomeRule->response_text,
            direction: 'outgoing',
            type: 'text',
            isFromContact: false,
            source: ChatMessageDTO::SOURCE_BOT
        ));

        $this->setCooldown($tenantId, $ticketId, $welcomeRule->id, (int) $welcomeRule->cooldown_seconds);
    }

    /**
     * Verifica se o corpo da mensagem corresponde ao gatilho da regra.
     *
     * @param  ChatChatbotRule  $rule  Regra a ser testada.
     * @param  string  $body  Texto da mensagem.
     * @return bool True se houver correspondência (insensível a maiúsculas/minúsculas).
     */
    private function matches(ChatChatbotRule $rule, string $body): bool
    {
        return stripos($body, $rule->trigger_text) !== false;
    }

    /**
     * Verifica se a regra está em período de cooldown para o ticket atual.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  UUID do ticket.
     * @param  string  $ruleId  UUID da regra.
     * @return bool True se estiver em cooldown, false caso contrário.
     */
    private function inCooldown(string $tenantId, string $ticketId, string $ruleId): bool
    {
        return ChatChatbotCooldown::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->where('rule_id', $ruleId)
            ->where('cooldown_until', '>', now())
            ->exists();
    }

    /**
     * Registra ou atualiza o período de cooldown para uma regra em um ticket.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  UUID do ticket.
     * @param  string  $ruleId  UUID da regra.
     * @param  int  $seconds  Duração do cooldown em segundos.
     */
    private function setCooldown(string $tenantId, string $ticketId, string $ruleId, int $seconds): void
    {
        $cooldown = ChatChatbotCooldown::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->where('rule_id', $ruleId)
            ->first();

        if ($cooldown) {
            $cooldown->update([
                'cooldown_until' => $seconds > 0 ? now()->addSeconds($seconds) : null,
            ]);
        } else {
            ChatChatbotCooldown::query()->create([
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketId,
                'rule_id' => $ruleId,
                'cooldown_until' => $seconds > 0 ? now()->addSeconds($seconds) : null,
            ]);
        }
    }
}
