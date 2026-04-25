<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\UpdateChatTicketAction;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Services\WebChatJwtService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebChatCloseController extends BaseController
{
    public function __construct(
        private readonly WebChatJwtService $jwtService,
        private readonly UpdateChatTicketAction $updateAction,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $token = $request->input('token');
        if (! is_string($token) || $token === '') {
            return $this->unauthorized('Token inválido ou expirado');
        }

        $payload = $this->jwtService->validateToken($token);
        if ($payload === null) {
            return $this->unauthorized('Token inválido ou expirado');
        }

        $sessionId = $payload['session_id'] ?? null;
        $tenantId = $payload['tenant_id'] ?? null;
        $ticketId = $payload['ticket_id'] ?? null;

        if (! is_string($sessionId) || $sessionId === '' || ! is_string($tenantId) || $tenantId === '' || ! is_string($ticketId) || $ticketId === '') {
            return $this->unauthorized('Token inválido: claims incompletas');
        }

        $session = ChatSession::query()
            ->where('id', $sessionId)
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->first();

        if (! $session) {
            return $this->notFound('Sessão não encontrada ou inativa');
        }

        $ticket = $this->updateAction->find($tenantId, $ticketId);

        if ((string) $ticket->status !== 'closed') {
            $ticket = $this->updateAction->updateStatus($ticket, 'closed', null, 'normal', null);
        }

        return $this->success([
            'ticketId' => (string) $ticket->id,
            'status' => (string) $ticket->status,
            'closedAt' => $ticket->closed_at?->toIso8601String(),
        ], 'Ticket fechado');
    }
}