<?php

declare(strict_types=1);

namespace Domain\Chat\Jobs;

use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatWebhookRouter;
use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job assíncrono que roteia uma mensagem recebida para o destino correto.
 *
 * Após a persistência de uma mensagem, este job carrega o ticket e delega
 * ao `ChatWebhookRouter` a decisão de encaminhar para IA, auto-reply ou
 * agente humano conforme as configurações do tenant.
 */
final class ConversationResolverJob implements ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket a ser roteado.
     * @param  string  $body  Conteúdo textual da mensagem recebida.
     * @param  array<string, mixed>  $context  Contexto adicional do webhook (ex.: direction, session_id).
     */
    public function __construct(
        private readonly string $tenantId,
        private readonly string $ticketId,
        private readonly string $body,
        private readonly array $context = [],
    ) {}

    /**
     * Carrega o ticket e executa o roteamento da conversa.
     */
    public function handle(ChatWebhookRouter $router): void
    {
        $ticket = ChatTicket::query()
            ->where('tenant_id', $this->tenantId)
            ->find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $router->routeInbound($this->tenantId, $ticket, $this->body, $this->context);
    }
}
