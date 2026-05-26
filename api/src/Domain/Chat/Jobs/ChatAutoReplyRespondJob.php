<?php

declare(strict_types=1);

namespace Domain\Chat\Jobs;

use Domain\Chat\Services\ChatAutoReplyResponder;
use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Executa a resposta de auto-reply de forma assíncrona.
 *
 * Despacha o `ChatAutoReplyResponder` na fila `auto-reply` para não bloquear
 * o ciclo de ingestão do webhook. Avalia regras configuradas pelo tenant e
 * envia as respostas automáticas cabíveis para o ticket.
 */
final class ChatAutoReplyRespondJob implements ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador do ticket.
     * @param  string  $body  Conteúdo da mensagem recebida.
     * @param  bool  $isFirstInteraction  Indica se é a primeira interação do ticket.
     */
    public function __construct(
        private readonly string $tenantId,
        private readonly string $ticketId,
        private readonly string $body,
        private readonly bool $isFirstInteraction = false,
    ) {
        $this->onQueue('auto-reply');
    }

    /**
     * Processa a geração e envio das respostas automáticas para o ticket.
     */
    public function handle(ChatAutoReplyResponder $responder): void
    {
        $responder->respond(
            $this->tenantId,
            $this->ticketId,
            $this->body,
            $this->isFirstInteraction,
        );
    }
}
