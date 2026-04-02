<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatTicketDTO;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Backward-compatible facade for chat ticket use-cases.
 *
 * Delegates all behavior to focused actions extracted in TASK-013-S2-CHAT.
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
     * @param  array<string, mixed>  $filters
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        return $this->listAction()->list($tenantId, $filters);
    }

    /**
     * @return array<string, int>
     */
    public function counts(string $tenantId): array
    {
        return $this->listAction()->counts($tenantId);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function init(string $tenantId, string $userId, array $filters = []): array
    {
        return $this->listAction()->init($tenantId, $userId, $filters);
    }

    public function create(string $tenantId, ChatTicketDTO $dto): ChatTicket
    {
        return $this->createAction()->create($tenantId, $dto);
    }

    public function find(string $tenantId, string $id): ChatTicket
    {
        return $this->updateAction()->find($tenantId, $id);
    }

    public function findOrCreateByRemoteJid(string $tenantId, ChatTicketDTO $dto): ChatTicket
    {
        return $this->createAction()->findOrCreateByRemoteJid($tenantId, $dto);
    }

    public function updateStatus(
        ChatTicket $ticket,
        string $status,
        ?string $reason = null,
        string $mode = 'normal',
        ?string $closedByUserId = null,
    ): ChatTicket {
        return $this->updateAction()->updateStatus($ticket, $status, $reason, $mode, $closedByUserId);
    }

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

    public function markAsRead(string $tenantId, string $id, ?ChatTicket $ticket = null): void
    {
        $this->updateAction()->markAsRead($tenantId, $id, $ticket);
    }

    public function findForRead(string $tenantId, string $id): ChatTicket
    {
        return $this->updateAction()->findForRead($tenantId, $id);
    }

    public function findForAuth(string $tenantId, string $id): ChatTicket
    {
        return ChatTicket::query()
            ->select(['id', 'tenant_id', 'instance_id', 'contact_id', 'assigned_to', 'status'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    public function open(string $tenantId, string $id, string $userId): ChatTicket
    {
        return $this->updateAction()->open($tenantId, $id, $userId);
    }

    public function activateHumanTakeover(string $tenantId, string $ticketId, ?string $userId = null): ChatTicket
    {
        return $this->assignAction()->activateHumanTakeover($tenantId, $ticketId, $userId);
    }

    public function releaseToAi(string $tenantId, string $ticketId, ?string $userId = null): ChatTicket
    {
        return $this->assignAction()->releaseToAi($tenantId, $ticketId, $userId);
    }

    private function listAction(): ListChatTicketsAction
    {
        app()->instance(ChatGatewayService::class, $this->gateway);
        app()->instance(ChatActivityBroadcastService::class, $this->activityBroadcast);

        return $this->listAction ??= app(ListChatTicketsAction::class);
    }

    private function createAction(): CreateChatTicketAction
    {
        app()->instance(ChatGatewayService::class, $this->gateway);
        app()->instance(ChatActivityBroadcastService::class, $this->activityBroadcast);

        return $this->createAction ??= app(CreateChatTicketAction::class);
    }

    private function updateAction(): UpdateChatTicketAction
    {
        app()->instance(ChatGatewayService::class, $this->gateway);
        app()->instance(ChatActivityBroadcastService::class, $this->activityBroadcast);

        return $this->updateAction ??= app(UpdateChatTicketAction::class);
    }

    private function assignAction(): AssignChatTicketAction
    {
        app()->instance(ChatGatewayService::class, $this->gateway);
        app()->instance(ChatActivityBroadcastService::class, $this->activityBroadcast);

        return $this->assignAction ??= app(AssignChatTicketAction::class);
    }

    private function sendAction(): SendTicketMessageAction
    {
        app()->instance(ChatGatewayService::class, $this->gateway);
        app()->instance(ChatActivityBroadcastService::class, $this->activityBroadcast);

        return $this->sendAction ??= app(SendTicketMessageAction::class);
    }
}
