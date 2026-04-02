<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatTicketActions;
use Domain\Chat\Actions\ChatTicketEvaluationActions;
use Domain\Chat\DTOs\ChatTicketEvaluationDTO;
use Domain\Chat\Http\Requests\ChatTicketEvaluationRequest;
use Domain\Chat\Http\Resources\ChatTicketEvaluationResource;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Avaliações de Tickets (CSAT).
 *
 * Permite listar avaliações recebidas e gerar registros de solicitação
 * de avaliação vinculados a tickets encerrados.
 *
 * @category Controllers
 */
final class ChatTicketEvaluationController extends BaseController
{
    public function __construct(
        private readonly ChatTicketEvaluationActions $actions,
        private readonly ChatTicketActions $ticketActions,
    ) {}

    /**
     * Listar todas as avaliações recebidas pelo tenant.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Lista paginada de avaliações.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \Domain\Chat\Models\ChatTicket::class);

        $tenantId = $this->tenantId($request);
        $paginator = $this->actions->list($tenantId);
        $paginator->getCollection()->transform(fn ($item) => new ChatTicketEvaluationResource($item));

        return $this->paginated($paginator);
    }

    /**
     * Criar uma nova solicitação ou registro de avaliação para um ticket.
     *
     * @param  ChatTicketEvaluationRequest  $request  Dados validados da avaliação.
     * @param  string  $ticketId  Identificador UUID do ticket vinculado.
     * @return JsonResponse Cadastro da avaliação realizado.
     */
    public function store(ChatTicketEvaluationRequest $request, string $ticketId): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $ticket = $this->ticketActions->find($tenantId, $ticketId);
        $this->authorize('update', $ticket);
        $evaluation = $this->actions->create(
            $tenantId,
            ChatTicketEvaluationDTO::fromRequest($request, $ticketId)
        );

        return $this->created(new ChatTicketEvaluationResource($evaluation), 'Avaliação registrada');
    }
}
