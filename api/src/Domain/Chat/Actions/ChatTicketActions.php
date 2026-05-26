<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatTicketDTO;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Fachada de compatibilidade retroativa para casos de uso de tickets de chat.
 *
 * Delega todo o comportamento para actions especializadas extraídas em TASK-013-S2-CHAT.
 * Mantida para evitar quebras em chamadores existentes durante a refatoração.
 *
 * @category Actions
 */
final class ChatTicketActions
{
    public function __construct(
        private readonly ChatGatewayService $gateway,
        private readonly ChatActivityBroadcastService $activityBroadcast,
        private ?ListChatTicketsAction $listAction = null,
        private ?CreateChatTicketAction $createAction = null,
        private ?UpdateChatTicketAction $updateAction = null,
        private ?AssignChatTicketAction $assignAction = null,
        private ?SendTicketMessageAction $sendAction = null,
    ) {}

    /**
     * Lista tickets do tenant com paginação e filtros.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        return $this->listAction()->list($tenantId, $filters);
    }

    /**
     * Retorna contagens de tickets por status para o tenant.
     *
     * @return array<string, int>
     */
    public function counts(string $tenantId): array
    {
        return $this->listAction()->counts($tenantId);
    }

    /**
     * Carrega estado inicial da caixa de entrada (tickets + contagens + configurações).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function init(string $tenantId, string $userId, array $filters = []): array
    {
        return $this->listAction()->init($tenantId, $userId, $filters);
    }

    /**
     * Cria um novo ticket de chat para o tenant.
     */
    public function create(string $tenantId, ChatTicketDTO $dto): ChatTicket
    {
        return $this->createAction()->create($tenantId, $dto);
    }

    /**
     * Localiza um ticket pelo ID com eager loading completo.
     */
    public function find(string $tenantId, string $id): ChatTicket
    {
        return $this->updateAction()->find($tenantId, $id);
    }

    /**
     * Localiza ou cria um ticket pelo remote_jid do canal.
     */
    public function findOrCreateByRemoteJid(string $tenantId, ChatTicketDTO $dto): ChatTicket
    {
        return $this->createAction()->findOrCreateByRemoteJid($tenantId, $dto);
    }

    /**
     * Atualiza o status do ticket, incluindo encerramento condicional.
     *
     * @param  string  $mode  'normal' ou 'forced'
     */
    public function updateStatus(
        ChatTicket $ticket,
        string $status,
        ?string $reason = null,
        string $mode = 'normal',
        ?string $closedByUserId = null,
    ): ChatTicket {
        return $this->updateAction()->updateStatus($ticket, $status, $reason, $mode, $closedByUserId);
    }

    /**
     * Transfere o ticket para outro atendente ou departamento.
     */
    public function transfer(ChatTicket $ticket, ?string $userId, ?string $departmentId): ChatTicket
    {
        return $this->assignAction()->transfer($ticket, $userId, $departmentId);
    }

    /**
     * @param  array<string, mixed>  $extraMetadata
     */
    public function sendConfiguredSystemMessage(
        ChatTicket $ticket,
        string $flagKey,
        string $messageKey,
        string $kind,
        array $extraMetadata = []
    ): void {
        $this->sendAction()->sendConfiguredSystemMessage($ticket, $flagKey, $messageKey, $kind, $extraMetadata);
    }

    /**
     * Marca todas as mensagens do ticket como lidas e sincroniza com o gateway.
     */
    public function markAsRead(string $tenantId, string $id, ?ChatTicket $ticket = null): void
    {
        $this->updateAction()->markAsRead($tenantId, $id, $ticket);
    }

    /**
     * Localiza ticket mínimo para operações de leitura.
     */
    public function findForRead(string $tenantId, string $id): ChatTicket
    {
        return $this->updateAction()->findForRead($tenantId, $id);
    }

    /**
     * Localiza ticket mínimo para verificação de autorização.
     */
    public function findForAuth(string $tenantId, string $id): ChatTicket
    {
        return ChatTicket::query()
            ->select(['id', 'tenant_id', 'instance_id', 'contact_id', 'assigned_to', 'status'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    /**
     * Abre o ticket e assume o atendimento pelo atendente informado.
     */
    public function open(string $tenantId, string $id, string $userId): ChatTicket
    {
        return $this->updateAction()->open($tenantId, $id, $userId);
    }

    /**
     * Ativa takeover humano, interrompendo o fluxo de IA para o ticket.
     */
    public function activateHumanTakeover(string $tenantId, string $ticketId, ?string $userId = null): ChatTicket
    {
        return $this->assignAction()->activateHumanTakeover($tenantId, $ticketId, $userId);
    }

    /**
     * Libera o ticket para retomada pelo fluxo de IA.
     */
    public function releaseToAi(string $tenantId, string $ticketId, ?string $userId = null): ChatTicket
    {
        return $this->assignAction()->releaseToAi($tenantId, $ticketId, $userId);
    }

    /**
     * Instancia ou retorna a action de listagem de tickets.
     */
    private function listAction(): ListChatTicketsAction
    {
        app()->instance(ChatGatewayService::class, $this->gateway);
        app()->instance(ChatActivityBroadcastService::class, $this->activityBroadcast);

        return $this->listAction ??= app(ListChatTicketsAction::class);
    }

    /**
     * Instancia ou retorna a action de criação de tickets.
     */
    private function createAction(): CreateChatTicketAction
    {
        app()->instance(ChatGatewayService::class, $this->gateway);
        app()->instance(ChatActivityBroadcastService::class, $this->activityBroadcast);

        return $this->createAction ??= app(CreateChatTicketAction::class);
    }

    /**
     * Instancia ou retorna a action de atualização de tickets.
     */
    private function updateAction(): UpdateChatTicketAction
    {
        app()->instance(ChatGatewayService::class, $this->gateway);
        app()->instance(ChatActivityBroadcastService::class, $this->activityBroadcast);

        return $this->updateAction ??= app(UpdateChatTicketAction::class);
    }

    /**
     * Instancia ou retorna a action de atribuição de tickets.
     */
    private function assignAction(): AssignChatTicketAction
    {
        app()->instance(ChatGatewayService::class, $this->gateway);
        app()->instance(ChatActivityBroadcastService::class, $this->activityBroadcast);

        return $this->assignAction ??= app(AssignChatTicketAction::class);
    }

    /**
     * Instancia ou retorna a action de envio de mensagens de sistema para tickets.
     */
    private function sendAction(): SendTicketMessageAction
    {
        app()->instance(ChatGatewayService::class, $this->gateway);
        app()->instance(ChatActivityBroadcastService::class, $this->activityBroadcast);

        return $this->sendAction ??= app(SendTicketMessageAction::class);
    }
}
