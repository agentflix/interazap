<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\AssignChatTicketAction;
use Domain\Chat\Actions\CreateChatTicketAction;
use Domain\Chat\Actions\EvaluateTicketCsatAction;
use Domain\Chat\Actions\ListChatMessagesAction;
use Domain\Chat\Actions\ListChatTicketsAction;
use Domain\Chat\Actions\ProcessTicketAttachmentAction;
use Domain\Chat\Actions\SendTicketMessageAction;
use Domain\Chat\Actions\UpdateChatTicketAction;
use Domain\Chat\DTOs\ChatTicketDTO;
use Domain\Chat\Http\Requests\ChatTicketCloseRequest;
use Domain\Chat\Http\Requests\ChatTicketStoreRequest;
use Domain\Chat\Http\Resources\ChatTicketResource;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Tickets de Chat.
 *
 * Centraliza a gestão de conversações (tickets), permitindo listagem filtrada,
 * abertura manual, encerramento, transferência e controle de lidos/não-lidos.
 *
 * @category Controllers
 */
final class ChatTicketController extends BaseController
{
    public function __construct(
        private readonly ListChatTicketsAction $listAction,
        private readonly CreateChatTicketAction $createAction,
        private readonly UpdateChatTicketAction $updateAction,
        private readonly AssignChatTicketAction $assignAction,
        private readonly SendTicketMessageAction $sendAction,
        private readonly ProcessTicketAttachmentAction $attachmentAction,
        private readonly EvaluateTicketCsatAction $evaluateAction,
        private readonly ListChatMessagesAction $listMessagesAction,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Listar os tickets do tenant com suporte a filtros e busca.
     *
     * @param  Request  $request  Solicitação HTTP (status, contact_id, instance_id, user_id, search).
     * @return JsonResponse Lista paginada de tickets formatados.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \Domain\Chat\Models\ChatTicket::class);

        $tenantId = (string) $request->user()->tenant_id;
        $filters = $request->only(['status', 'contact_id', 'instance_id', 'user_id', 'search', 'sentiment', 'sort_by', 'agent_id', 'from', 'to']);
        $filters['group_by_contact'] = $request->boolean('group_by_contact', true);
        $filters['per_page'] = (int) $request->input('per_page', 15);
        $paginator = $this->listAction->list($tenantId, $filters);
        $counts = $this->listAction->counts($tenantId);
        $latestMessages = $paginator->getCollection()
            ->map(fn ($ticket) => $ticket->latestMessage)
            ->filter();
        $quotedMap = $this->listMessagesAction->prefetchQuotedMessages($latestMessages);

        $paginator->getCollection()->transform(
            fn ($item) => (new ChatTicketResource($item))->withQuotedMap($quotedMap)
        );

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'counts' => $counts,
        ], 200);
    }

    /**
     * Carregar payload inicial agregado da inbox de chat.
     *
     * Retorna tickets, contadores e preferências do usuário em uma única chamada.
     *
     * @param  Request  $request  Solicitação HTTP com filtros de ticket.
     * @return JsonResponse Payload inicial do chat.
     */
    public function init(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \Domain\Chat\Models\ChatTicket::class);

        $tenantId = (string) $request->user()->tenant_id;
        $userId = (string) $request->user()->id;
        $filters = $request->only(['status', 'contact_id', 'instance_id', 'user_id', 'search', 'sentiment', 'sort_by']);
        $filters['per_page'] = (int) $request->input('per_page', 15);

        $payload = $this->listAction->init($tenantId, $userId, $filters);

        /** @var \Illuminate\Support\Collection<int, \Domain\Chat\Models\ChatTicket> $tickets */
        $tickets = collect($payload['tickets']['data']);

        $latestMessages = $tickets
            ->map(fn ($ticket) => $ticket->latestMessage)
            ->filter();
        $quotedMap = $this->listMessagesAction->prefetchQuotedMessages($latestMessages);

        $payload['tickets']['data'] = $tickets
            ->map(fn ($item) => (new ChatTicketResource($item))->withQuotedMap($quotedMap)->toArray($request))
            ->values()
            ->all();

        return $this->success($payload);
    }

    /**
     * Criar um novo ticket manual para um contato.
     *
     * @param  ChatTicketStoreRequest  $request  Dados validados do ticket.
     * @return JsonResponse Recurso do ticket criado.
     */
    public function store(ChatTicketStoreRequest $request): JsonResponse
    {
        $this->authorize('create', \Domain\Chat\Models\ChatTicket::class);

        $tenantId = (string) $request->user()->tenant_id;
        $ticket = $this->createAction->create($tenantId, ChatTicketDTO::fromRequest($request));
        $this->audit->log($request->user(), $tenantId, 'chat.tickets.created', $ticket);

        return $this->created(new ChatTicketResource($ticket), 'Ticket criado');
    }

    /**
     * Exibir os dados e histórico resumido de um ticket específico.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID do ticket.
     * @return JsonResponse Objeto do ticket.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $ticket = $this->updateAction->find($tenantId, $id);
        $this->authorize('view', $ticket);

        return $this->success(new ChatTicketResource($ticket), 'Ticket carregado');
    }

    /**
     * Encerrar um ticket formalmente.
     *
     * Supports forced close mode (mode=forced) which skips customer notification.
     *
     * @param  ChatTicketCloseRequest  $request  Solicitação HTTP validada.
     * @param  string  $id  Identificador UUID do ticket.
     * @return JsonResponse Ticket com status atualizado.
     */
    public function close(ChatTicketCloseRequest $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $ticket = $this->updateAction->find($tenantId, $id);
        $this->authorize('update', $ticket);

        $mode = (string) ($request->validated('mode') ?? 'normal');
        $reason = $request->validated('reason');
        $closedByUserId = (string) $request->user()->id;

        // Enforce forceClose policy for forced mode
        if ($mode === 'forced') {
            $this->authorize('forceClose', $ticket);
        }

        $ticket = $this->updateAction->updateStatus($ticket, 'closed', $reason, $mode, $closedByUserId);
        $this->audit->log($request->user(), $tenantId, 'chat.tickets.closed', $ticket, [
            'reason' => $reason,
            'mode' => $mode,
            'closed_by_user_id' => $closedByUserId,
        ]);

        return $this->success(new ChatTicketResource($ticket), 'Ticket fechado');
    }

    /**
     * Reabrir um ticket ou assumir um ticket pendente.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID do ticket.
     * @return JsonResponse Ticket aberto/assumido.
     */
    public function open(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $ticket = $this->updateAction->find($tenantId, $id);
        $this->authorize('update', $ticket);
        $ticket = $this->updateAction->open($tenantId, $id, (string) $request->user()->id);

        $this->audit->log($request->user(), $tenantId, 'chat.tickets.opened', $ticket);

        return $this->success(new ChatTicketResource($ticket), 'Ticket aberto');
    }

    /**
     * Transferir o ticket para outro usuário ou departamento.
     *
     * @param  Request  $request  Solicitação HTTP (user_id, department_id).
     * @param  string  $id  Identificador UUID do ticket.
     * @return JsonResponse Ticket transferido.
     */
    public function transfer(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $ticket = $this->updateAction->find($tenantId, $id);
        $this->authorize('update', $ticket);

        $ticket = $this->assignAction->transfer(
            $ticket,
            $request->input('user_id'),
            $request->input('department_id')
        );
        $this->audit->log($request->user(), $tenantId, 'chat.tickets.transferred', $ticket, $ticket->getChanges());

        return $this->success(new ChatTicketResource($ticket), 'Ticket transferido');
    }

    /**
     * Marcar todas as mensagens do ticket como lidas.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID do ticket.
     * @return JsonResponse Confirmação de operação.
     */
    public function read(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $ticket = $this->updateAction->findForRead($tenantId, $id);
        $this->authorize('view', $ticket);

        $this->updateAction->markAsRead($tenantId, $id, $ticket);

        return $this->success([], 'Chat marcado como lido');
    }
}
