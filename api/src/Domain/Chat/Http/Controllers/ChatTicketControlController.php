<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatTicketActions;
use Domain\Chat\Http\Resources\ChatTicketResource;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Controle de Atendimento de Tickets.
 *
 * Gerencia a alternância entre atendimento humano e automação (IA/Bot),
 * permitindo que agentes assumam o controle ou devolvam para a IA.
 *
 * @category Controllers
 */
final class ChatTicketControlController extends BaseController
{
    public function __construct(
        private readonly ChatTicketActions $actions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Ativar o modo de atendimento humano (Human Takeover).
     *
     * Bloqueia todas as automações (Bot/IA) para este ticket até que seja
     * liberado explicitamente via releaseToAi.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID do ticket.
     * @return JsonResponse Ticket atualizado com human_takeover_at preenchido.
     */
    public function takeover(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = (string) $user->tenant_id;

        $ticket = $this->actions->find($tenantId, $id);

        $this->authorize('update', $ticket);

        $ticket = $this->actions->activateHumanTakeover(
            $tenantId,
            $id,
            (string) $user->id
        );

        $this->audit->log($user, $tenantId, 'chat.tickets.takeover', $ticket, [
            'ticket_id' => $id,
            'ticket_protocol' => $ticket->protocol,
        ]);

        return $this->success(
            new ChatTicketResource($ticket),
            'Atendimento humano ativado. Automações pausadas.'
        );
    }

    /**
     * Liberar o ticket para controle da IA/Bot.
     *
     * Remove o bloqueio de atendimento humano, permitindo que automações
     * respondam novamente às mensagens do cliente.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID do ticket.
     * @return JsonResponse Ticket atualizado com human_takeover_at nulo.
     */
    public function releaseToAi(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = (string) $user->tenant_id;

        $ticket = $this->actions->find($tenantId, $id);

        $this->authorize('update', $ticket);

        $ticket = $this->actions->releaseToAi(
            $tenantId,
            $id,
            (string) $user->id
        );

        $this->audit->log($user, $tenantId, 'chat.tickets.release_to_ai', $ticket, [
            'ticket_id' => $id,
            'ticket_protocol' => $ticket->protocol,
        ]);

        return $this->success(
            new ChatTicketResource($ticket),
            'Controle devolvido para IA. Automações reativadas.'
        );
    }
}
