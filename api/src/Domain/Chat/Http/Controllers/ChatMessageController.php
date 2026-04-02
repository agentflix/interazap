<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatMessageDeleteActions;
use Domain\Chat\Actions\ChatMessageEditActions;
use Domain\Chat\Actions\ChatMessageReactionActions;
use Domain\Chat\Actions\ChatTicketActions;
use Domain\Chat\Actions\ListChatMessagesAction;
use Domain\Chat\Actions\SendChatMessageAction;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Http\Requests\ChatMessageContactRequest;
use Domain\Chat\Http\Requests\ChatMessageEditRequest;
use Domain\Chat\Http\Requests\ChatMessageLocationRequest;
use Domain\Chat\Http\Requests\ChatMessageReactRequest;
use Domain\Chat\Http\Requests\ChatMessageStoreRequest;
use Domain\Chat\Http\Resources\ChatMessageResource;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Controlador de Mensagens de Chat.
 *
 * Gerencia a troca de mensagens dentro de um ticket, incluindo listagem
 * do histórico de conversação e o envio de novas mensagens (texto e arquivos).
 *
 * @category Controllers
 */
final class ChatMessageController extends BaseController
{
    public function __construct(
        private readonly ListChatMessagesAction $listMessagesAction,
        private readonly SendChatMessageAction $sendMessageAction,
        private readonly ChatMessageReactionActions $reactionActions,
        private readonly ChatMessageEditActions $editActions,
        private readonly ChatMessageDeleteActions $deleteActions,
        private readonly ChatTicketActions $ticketActions,
        private readonly AuditLogger $audit,
        private readonly PlatformPlanEnforcementService $planEnforcementService,
    ) {}

    /**
     * Listar o histórico de mensagens de um ticket específico.
     *
     * @param  Request  $request  Solicitação HTTP com filtros (direção, tipo).
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @return JsonResponse Lista de mensagens paginada.
     */
    public function index(Request $request, string $ticketId): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $ticket = $this->ticketActions->findForAuth($tenantId, $ticketId);
        $this->authorize('viewAny', [\Domain\Chat\Models\ChatMessage::class, $ticket]);

        $filters = $request->only(['direction', 'type']);
        $includeMetadata = $request->boolean('include_full_metadata');
        $after = $request->query('after');
        $before = $request->query('before');
        $since = $request->query('since');
        $limit = (int) $request->query('limit', 30);

        if ((is_string($after) && $after !== '') || (is_string($before) && $before !== '') || (is_string($since) && $since !== '')) {
            $cursorPayload = $this->listMessagesAction->listByTicketCursor(
                $tenantId,
                $ticketId,
                $filters,
                $limit,
                is_string($after) ? $after : null,
                is_string($before) ? $before : null,
                is_string($since) ? $since : null,
            );

            $collection = $cursorPayload['messages'];
            $quotedMap = $this->listMessagesAction->prefetchQuotedMessages($collection);
            $messages = $collection->map(
                fn ($item): array => (new ChatMessageResource($item))
                    ->withQuotedMap($quotedMap)
                    ->toArray($request)
            )->values();

            return $this->success([
                'messages' => $messages,
                'meta' => $cursorPayload['meta'],
            ]);
        }

        $paginator = $this->listMessagesAction->listByTicket($tenantId, $ticketId, $filters, $includeMetadata);

        // Pre-fetch quoted messages in batch to avoid N+1 queries
        $collection = $paginator->getCollection();
        $quotedMap = $this->listMessagesAction->prefetchQuotedMessages($collection);

        $messages = $collection->map(
            fn ($item): array => (new ChatMessageResource($item))
                ->withQuotedMap($quotedMap)
                ->toArray($request)
        );

        return $this->success([
            'messages' => $messages,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Enviar uma nova mensagem através de um ticket.
     *
     * @param  ChatMessageStoreRequest  $request  Dados validados (conteúdo, destinatário, etc).
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @return JsonResponse Mensagem registrada e enviada.
     */
    public function store(ChatMessageStoreRequest $request, string $ticketId): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $ticket = $this->ticketActions->find($tenantId, $ticketId);
        $this->authorize('create', [\Domain\Chat\Models\ChatMessage::class, $ticket]);

        $validated = $request->validated();
        $payload = ChatMessageDTO::fromArray([
            ...$validated,
            'ticket_id' => $ticketId,
            'direction' => $validated['direction'] ?? 'outgoing',
            'is_from_contact' => $validated['is_from_contact'] ?? false,
            'user_id' => $validated['user_id'] ?? (string) $request->user()->id,
            'contact_id' => $validated['contact_id'] ?? $ticket->contact_id,
            'source' => ChatMessageDTO::SOURCE_AGENT,
        ]);

        $message = $this->sendMessageAction->create(
            $tenantId,
            $payload
        );
        $this->audit->log($request->user(), $tenantId, 'chat.messages.created', $message);

        return $this->created(new ChatMessageResource($message), 'Mensagem registrada');
    }

    /**
     * Obter dados de metadados de mídia de uma mensagem específica.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @param  string  $messageId  Identificador UUID da mensagem.
     * @return JsonResponse URL e atributos do arquivo de mídia.
     */
    public function media(Request $request, string $ticketId, string $messageId): JsonResponse
    {
        $this->authorize('view', \Domain\Chat\Models\ChatMessage::class);
        $tenantId = (string) $request->user()->tenant_id;

        if (! $this->planEnforcementService->canDownloadFile($tenantId)) {
            return $this->forbidden('Storage esgotado. Faça upgrade para baixar arquivos.');
        }

        $message = \Domain\Chat\Models\ChatMessage::query()->where('id', $messageId)
            ->where('ticket_id', $ticketId)
            ->where('tenant_id', $tenantId)
            ->with('extended')
            ->firstOrFail();

        if (! $message->file_url) {
            return response()->json(['message' => 'No media'], 404);
        }

        $url = $message->file_url;

        // For local storage files, generate temporary signed URL
        if (str_contains((string) $url, '/storage/chat/') && preg_match('#/storage/(.+)$#', (string) $url, $matches)) {
            $path = $matches[1];
            if (Storage::disk('public')->exists($path)) {
                $url = URL::temporarySignedRoute(
                    'chat.media.download',
                    now()->addMinutes(30),
                    array_filter([
                        'path' => $path,
                        'name' => $message->file_name,
                    ])
                );
            }
        }

        return $this->success([
            'url' => $url,
            'file_name' => $message->file_name,
            'mime_type' => $message->mime_type,
            'size' => $message->file_size,
        ]);
    }

    /**
     * Reagir a uma mensagem com emoji.
     *
     * @param  ChatMessageReactRequest  $request  Solicitação HTTP.
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @param  string  $messageId  Identificador UUID da mensagem.
     * @return JsonResponse Resultado da reação.
     */
    public function react(ChatMessageReactRequest $request, string $ticketId, string $messageId): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $this->ticketActions->find($tenantId, $ticketId);
        $this->authorize('update', \Domain\Chat\Models\ChatMessage::class);

        $validated = $request->validated();
        $emoji = (string) ($validated['emoji'] ?? '');
        $result = $this->reactionActions->react($tenantId, $messageId, $emoji);

        if (! $result['success']) {
            return $this->error($result['error'] ?? 'Falha ao reagir', 400);
        }

        return $this->success($result, 'Reação enviada');
    }

    /**
     * Editar uma mensagem enviada.
     *
     * @param  ChatMessageEditRequest  $request  Solicitação HTTP.
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @param  string  $messageId  Identificador UUID da mensagem.
     * @return JsonResponse Resultado da edição.
     */
    public function edit(ChatMessageEditRequest $request, string $ticketId, string $messageId): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $this->ticketActions->find($tenantId, $ticketId);
        $this->authorize('update', \Domain\Chat\Models\ChatMessage::class);

        $validated = $request->validated();
        $result = $this->editActions->edit($tenantId, $messageId, (string) $validated['content']);

        if (! $result['success']) {
            return $this->error($result['error'] ?? 'Falha ao editar', 400);
        }

        return $this->success($result, 'Mensagem editada');
    }

    /**
     * Excluir uma mensagem para todos.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @param  string  $messageId  Identificador UUID da mensagem.
     * @return JsonResponse Resultado da exclusão.
     */
    public function destroy(Request $request, string $ticketId, string $messageId): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $this->ticketActions->find($tenantId, $ticketId);
        $this->authorize('delete', \Domain\Chat\Models\ChatMessage::class);

        $deletedBy = (string) $request->user()->id;
        $result = $this->deleteActions->delete($tenantId, $messageId, $deletedBy);

        if (! $result['success']) {
            return $this->error($result['error'] ?? 'Falha ao excluir', 400);
        }

        return $this->success($result, 'Mensagem excluída');
    }

    /**
     * Enviar cartão de contato (vCard).
     *
     * @param  ChatMessageContactRequest  $request  Solicitação HTTP.
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @return JsonResponse Mensagem criada.
     */
    public function sendContact(ChatMessageContactRequest $request, string $ticketId): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $ticket = $this->ticketActions->find($tenantId, $ticketId);
        $this->authorize('create', [\Domain\Chat\Models\ChatMessage::class, $ticket]);

        $validated = $request->validated();
        $contact = $validated['contact'] ?? [];

        $payload = ChatMessageDTO::fromArray([
            'ticket_id' => $ticketId,
            'direction' => 'outgoing',
            'is_from_contact' => false,
            'user_id' => (string) $request->user()->id,
            'contact_id' => $ticket->contact_id,
            'content' => (string) ($contact['fullName'] ?? ''),
            'metadata' => ['contact' => $contact],
        ]);

        $message = $this->sendMessageAction->sendContact($tenantId, $payload);
        $this->audit->log($request->user(), $tenantId, 'chat.messages.contact_sent', $message);

        return $this->created(new ChatMessageResource($message), 'Contato enviado');
    }

    /**
     * Enviar localização geográfica.
     *
     * @param  ChatMessageLocationRequest  $request  Solicitação HTTP.
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @return JsonResponse Mensagem criada.
     */
    public function sendLocation(ChatMessageLocationRequest $request, string $ticketId): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $ticket = $this->ticketActions->find($tenantId, $ticketId);
        $this->authorize('create', [\Domain\Chat\Models\ChatMessage::class, $ticket]);

        $validated = $request->validated();
        $location = $validated['location'] ?? [];

        $payload = ChatMessageDTO::fromArray([
            'ticket_id' => $ticketId,
            'direction' => 'outgoing',
            'is_from_contact' => false,
            'user_id' => (string) $request->user()->id,
            'contact_id' => $ticket->contact_id,
            'content' => (string) ($location['name'] ?? 'Localização'),
            'metadata' => ['location' => $location],
        ]);

        $message = $this->sendMessageAction->sendLocation($tenantId, $payload);
        $this->audit->log($request->user(), $tenantId, 'chat.messages.location_sent', $message);

        return $this->created(new ChatMessageResource($message), 'Localização enviada');
    }
}
