<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Services\WebChatJwtService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Mensagens Webchat (leitura pública).
 *
 * Permite que o visitante carregue o histórico de mensagens da sessão,
 * autenticado via JWT de sessão webchat (token gerado em WebChatSessionController).
 *
 * @category Controllers
 */
final class WebChatMessagesController extends BaseController
{
    public function __construct(
        private readonly WebChatJwtService $jwtService,
    ) {}

    /**
     * Listar mensagens da sessão webchat.
     *
     * GET /api/webchat/sessions/{id}/messages?token={jwt}
     *
     * Retorna mensagens do ticket associado à sessão, excluindo:
     *  - mensagens internas (internal_note)
     *  - mensagens deletadas
     *
     * @param  string  $id  UUID da sessão
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $token = $request->query('token');
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

        if (
            ! is_string($sessionId) || $sessionId === ''
            || ! is_string($tenantId) || $tenantId === ''
            || ! is_string($ticketId) || $ticketId === ''
        ) {
            return $this->unauthorized('Token inválido: claims incompletas');
        }

        // Garante que o ID da URL bate com o token para evitar IDOR
        if ($sessionId !== $id) {
            return $this->unauthorized('Sessão não corresponde ao token');
        }

        $session = ChatSession::query()
            ->where('id', $sessionId)
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->first();

        if (! $session) {
            return $this->notFound('Sessão não encontrada ou inativa');
        }

        $messages = ChatMessage::query()
            ->where('ticket_id', $ticketId)
            ->where('tenant_id', $tenantId)
            ->where('type', '!=', 'internal_note')
            ->where('is_deleted', false)
            ->with('extended')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn (ChatMessage $msg) => [
                'id' => (string) $msg->id,
                'content' => (string) ($msg->content ?? ''),
                // Perspectiva do frontend (visitante): inverte a direção do banco.
                // DB 'incoming' = plataforma recebeu do visitante → 'outgoing' no widget.
                // DB 'outgoing' = plataforma enviou ao visitante  → 'incoming' no widget.
                'direction' => $msg->direction === 'incoming' ? 'outgoing' : 'incoming',
                'source' => (string) ($msg->source ?? 'ai'),
                'type' => $this->resolveType($msg->type),
                'status' => (string) ($msg->status ?? 'sent'),
                'fileUrl' => $msg->file_url,
                'mimeType' => $msg->mime_type,
                'fileName' => $msg->file_name,
                'createdAt' => $msg->created_at?->toIso8601String(),
                'sessionId' => $sessionId,
            ]);

        return $this->success($messages, 'Mensagens obtidas');
    }

    /**
     * Normaliza o tipo de mensagem para o contrato esperado pelo frontend WebChat.
     */
    private function resolveType(string $type): string
    {
        return match ($type) {
            'image' => 'image',
            'video' => 'video',
            'audio', 'ptt' => 'audio',
            'document', 'file' => 'file',
            default => 'text',
        };
    }
}
