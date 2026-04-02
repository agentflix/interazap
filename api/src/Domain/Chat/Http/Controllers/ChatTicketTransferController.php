<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatTicketActions;
use Domain\Chat\Actions\ChatTicketTransferActions;
use Domain\Chat\Http\Requests\ChatTicketTransferRequest;
use Domain\Chat\Http\Resources\ChatTicketTransferResource;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Transferências de Tickets.
 *
 * Gerencia a movimentação de uma conversa entre diferentes atendentes,
 * mantendo o histórico de quem transferiu e o motivo.
 *
 * @category Controllers
 */
final class ChatTicketTransferController extends BaseController
{
    public function __construct(
        private readonly ChatTicketActions $ticketActions,
        private readonly ChatTicketTransferActions $transferActions,
    ) {}

    /**
     * Listar o histórico de transferências de um ticket.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @return JsonResponse Lista paginada de transferências.
     */
    public function index(Request $request, string $ticketId): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $ticket = $this->ticketActions->find($tenantId, $ticketId);
        $this->authorize('view', $ticket);
        $paginator = $this->transferActions->list($ticket->id);
        $paginator->getCollection()->transform(fn ($item) => new ChatTicketTransferResource($item));

        return $this->paginated($paginator);
    }

    /**
     * Solicitar a transferência de um ticket para outro atendente.
     *
     * @param  ChatTicketTransferRequest  $request  Dados validados (to_user_id, reason).
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @return JsonResponse Registro de transferência criado.
     */
    public function store(ChatTicketTransferRequest $request, string $ticketId): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $ticket = $this->ticketActions->find($tenantId, $ticketId);
        $this->authorize('transfer', $ticket);

        $transfer = $this->transferActions->transfer(
            $ticket,
            (string) $request->input('to_user_id'),
            (string) $request->input('reason')
        );

        return $this->created(new ChatTicketTransferResource($transfer), 'Ticket transferido');
    }
}
