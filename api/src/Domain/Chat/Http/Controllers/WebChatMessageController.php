<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ProcessChatMessageAction;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Services\ChatAutopilotResponder;
use Domain\Chat\Services\ChatAutoReplyResponder;
use Domain\Chat\Services\WebChatJwtService;
use Domain\Platform\Services\PlatformPlanEnforcementService;
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
        private readonly ChatAutoReplyResponder $autoReplyResponder,
        private readonly ProcessChatMessageAction $processAction,
        private readonly PlatformPlanEnforcementService $planEnforcement,
    ) {}

    /**
     * Receber mensagem do visitante webchat.
     *
     * POST /api/webchat/messages
     *
     * @return JsonResponse Message acknowledgement payload.
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
            'type' => $validated['type'],
            'file_url' => $validated['file_url'],
            'file_name' => $validated['file_name'],
            'mime_type' => $validated['mime_type'],
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

        // Emitir evento realtime para que o agente veja a mensagem sem F5
        $ticket = \Domain\Chat\Models\ChatTicket::query()
            ->where('id', $ticketId)
            ->where('tenant_id', $tenantId)
            ->with(['latestMessage', 'contact'])
            ->first();

        // Atualizar last_message_at do ticket para que apareça no topo da lista
        // do atendente e tenha o preview correto da última mensagem.
        if ($ticket !== null) {
            $ticket->forceFill(['last_message_at' => $message->created_at])->save();
        }

        $this->processAction->emitNewMessageEvent($message, $ticket);

        if ($ticket !== null && $ticket->human_takeover_at !== null) {
            Log::info('[WebChat] Automação ignorada: ticket sob atendimento humano', [
                'ticket_id' => $ticketId,
                'tenant_id' => $tenantId,
                'human_takeover_at' => (string) $ticket->human_takeover_at,
            ]);

            return $this->created([
                'messageId' => (string) $message->id,
            ], 'Mensagem enviada');
        }

        // Para mensagens de texto:
        // - plano com IA: dispara Autopilot
        // - plano sem IA: fallback para Auto Reply (chatbot)
        if ($validated['file_url'] === null) {
            if ($this->planEnforcement->isAiEnabled($tenantId)) {
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
            } else {
                $isFirstInteraction = ChatMessage::query()
                    ->where('ticket_id', $ticketId)
                    ->where('tenant_id', $tenantId)
                    ->where('is_from_contact', true)
                    ->count() === 1;

                $this->autoReplyResponder->dispatch(
                    $tenantId,
                    $ticketId,
                    $validated['content'],
                    $isFirstInteraction,
                );
            }
        }

        return $this->created([
            'messageId' => (string) $message->id,
        ], 'Mensagem enviada');
    }

    /**
     * Validar request de envio de mensagem.
     *
     * Suporta dois modos mutuamente exclusivos:
     *   - Texto: { token, content }
     *   - Mídia: { token, file_url, mime_type, type }
     *
     * @return array<string, mixed>
     */
    private function validateStoreRequest(Request $request): array
    {
        $token = $request->input('token');
        if (! is_string($token) || $token === '') {
            abort(400, 'token é obrigatório');
        }

        $fileUrl = $request->input('file_url');

        // Modo mídia
        if ($fileUrl !== null) {
            if (! is_string($fileUrl) || $fileUrl === '') {
                abort(400, 'file_url deve ser uma string não vazia');
            }

            $mimeType = $request->input('mime_type');
            if (! is_string($mimeType) || $mimeType === '') {
                abort(400, 'mime_type é obrigatório para mensagens de mídia');
            }

            $type = $request->input('type');
            $allowedTypes = ['image', 'video', 'audio', 'document'];
            if (! is_string($type) || ! in_array($type, $allowedTypes, true)) {
                abort(400, 'type inválido. Valores permitidos: '.implode(', ', $allowedTypes));
            }

            $fileName = $request->input('file_name');
            if ($fileName !== null && ! is_string($fileName)) {
                abort(400, 'file_name deve ser uma string');
            }

            return [
                'token' => $token,
                'content' => '',
                'file_url' => $fileUrl,
                'file_name' => $fileName,
                'mime_type' => $mimeType,
                'type' => $type,
                'client_message_id' => $request->input('client_message_id'),
            ];
        }

        // Modo texto
        $content = $request->input('content');
        if (! is_string($content) || trim($content) === '') {
            abort(400, 'content é obrigatório e não pode ser vazio');
        }

        return [
            'token' => $token,
            'content' => trim($content),
            'file_url' => null,
            'file_name' => null,
            'mime_type' => null,
            'type' => 'text',
            'client_message_id' => $request->input('client_message_id'),
        ];
    }
}
