<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatPresenceActions;
use Domain\Chat\Actions\ChatTicketActions;
use Domain\Chat\DTOs\ChatPresenceDTO;
use Domain\Chat\Http\Requests\ChatPresenceRequest;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Controlador de Presença de Chat.
 *
 * Permite simular estados de interação do atendente (ex: "digitando..." ou "gravando áudio...")
 * na conversa do cliente, aumentando a percepção de atendimento humano.
 *
 * @category Controllers
 */
final class ChatPresenceController extends BaseController
{
    public function __construct(
        private readonly ChatPresenceActions $presenceActions,
        private readonly ChatTicketActions $ticketActions,
    ) {}

    /**
     * Enviar um evento de presença para o contato vinculado ao ticket.
     *
     * @param  ChatPresenceRequest  $request  Dados validados (type: typing, recording, available).
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @return JsonResponse Confirmação de envio do estado de presença.
     */
    public function store(ChatPresenceRequest $request, string $ticketId): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $ticket = $this->ticketActions->find($tenantId, $ticketId);
        $this->authorize('view', $ticket);

        $dto = ChatPresenceDTO::fromRequest($request, $ticketId);
        $result = $this->presenceActions->send($tenantId, $dto);

        return $this->success($result, 'Presença enviada');
    }
}
