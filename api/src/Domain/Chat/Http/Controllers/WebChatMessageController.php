<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Services\ChatAutopilotResponder;
use Domain\Chat\Services\WebChatJwtService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Controlador de Mensagens Webchat.
 *
 * Recebe mensagens de visitantes webchat, persiste e dispara
 * resposta do autopilot (IA).
 *
 * @category Controllers
 */
final class WebChatMessageController extends BaseController
{
    public function __construct(
        private readonly WebChatJwtService $jwtService,
        private readonly ChatAutopilotResponder $autopilotResponder,
    ) {}

    /**
     * Receber mensagem do visitante webchat.
     *
     * POST /api/webchat/messages
     *
     * @param  Request  $request
     * @return JsonResponse{messageId: string}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateStoreRequest($request);

        // Validar JWT token
        $payload = $this->jwtService->validateToken($validated['token']);
        if ($payload === null) {
            return $this->unauthorized('Token inválido ou expirado');
        }

        $sessionId = $payload['session_id'] ?? null;
        $tenantId = $payload['tenant_id'] ?? null;
        $ticketId = $payload['ticket_id'] ?? null;

        if (! $sessionId || ! $tenantId || ! $ticketId) {
            return $this->unauthorized('Token inválido: claims incompletas');
        }

        // Verificar se sessão existe e está ativa
        $session = ChatSession::query()
            ->where('id', $sessionId)
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->first();

        if (! $session) {
            return $this->notFound('Sessão não encontrada ou inativa');
        }

        // Atualizar last_activity_at
        $session->touchLastActivity();

        // Criar mensagem no banco
        $message = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'ticket_id' => $ticketId,
            'contact_id' => $session->contact_id,
            'content' => $validated['content'],
            'direction' => 'incoming',
            'type' => 'text',
            'is_from_contact' => true,
            'source' => 'webchat',
            'status' => 'received',
            'metadata' => [
                'session_id' => $sessionId,
                'client_message_id' => $validated['client_message_id'],
            ],
        ]);

        Log::info('[WebChat] Mensagem recebida', [
            'message_id' => (string) $message->id,
            'ticket_id' => $ticketId,
            'session_id' => $sessionId,
            'tenant_id' => $tenantId,
        ]);

        // Dispara ChatAutopilotResponder para resposta da IA
        $this->autopilotResponder->respond(
            tenantId: $tenantId,
            ticketId: $ticketId,
            body: $validated['content'],
            context: [
                'session_id' => $sessionId,
                'message_id' => (string) $message->id,
                'source' => 'webchat',
            ],
        );

        return $this->created([
            'messageId' => (string) $message->id,
        ], 'Mensagem enviada');
    }

    /**
     * Validar request de envio de mensagem.
     *
     * @return array<string, mixed>
     */
    private function validateStoreRequest(Request $request): array
    {
        $content = $request->input('content');
        if (! is_string($content) || trim($content) === '') {
            abort(400, 'content é obrigatório e não pode ser vazio');
        }

        $token = $request->input('token');
        if (! is_string($token) || $token === '') {
            abort(400, 'token é obrigatório');
        }

        return [
            'content' => trim($content),
            'token' => $token,
            'client_message_id' => $request->input('client_message_id'),
        ];
    }
}
