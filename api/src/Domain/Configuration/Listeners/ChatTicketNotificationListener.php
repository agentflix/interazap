<?php

declare(strict_types=1);

namespace Domain\Configuration\Listeners;

use Domain\Chat\Models\ChatTicket;
use Domain\Configuration\Events\TicketAssignedEvent;
use Domain\Configuration\Events\TicketClosedEvent;
use Domain\Configuration\Events\TicketCreatedEvent;
use Domain\Configuration\Services\NotificationDispatcherService;

/**
 * Listener que transforma eventos de ticket em notificações de configuração.
 */
final class ChatTicketNotificationListener
{
    public function __construct(
        private readonly NotificationDispatcherService $dispatcher,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(TicketCreatedEvent|TicketAssignedEvent|TicketClosedEvent $event): void
    {
        if ($event instanceof TicketCreatedEvent) {
            $this->handleCreated($event);

            return;
        }

        if ($event instanceof TicketAssignedEvent) {
            $this->handleAssigned($event);

            return;
        }

        $this->handleClosed($event);
    }

    private function handleCreated(TicketCreatedEvent $event): void
    {
        $ticket = ChatTicket::query()
            ->where('tenant_id', $event->tenantId)
            ->with('extended')
            ->find($event->ticketId);

        if (! $ticket) {
            return;
        }

        $title = 'Novo ticket criado';
        $body = $ticket->subject ?: 'Um novo atendimento foi aberto.';

        $this->dispatcher->dispatch(
            tenantId: $event->tenantId,
            userIds: '*',
            type: 'new_ticket',
            title: $title,
            body: $body,
            data: [
                'ticket_id' => (string) $ticket->id,
                'status' => $ticket->status,
                'assigned_to' => $ticket->assigned_to,
            ],
            priority: 'high',
        );
    }

    private function handleAssigned(TicketAssignedEvent $event): void
    {
        $ticket = ChatTicket::query()
            ->where('tenant_id', $event->tenantId)
            ->with('extended')
            ->find($event->ticketId);

        if (! $ticket) {
            return;
        }

        $this->dispatcher->dispatch(
            tenantId: $event->tenantId,
            userIds: [$event->userId],
            type: 'ticket_assigned',
            title: 'Ticket atribuído para você',
            body: $ticket->subject ?: 'Você recebeu um novo ticket.',
            data: [
                'ticket_id' => (string) $ticket->id,
                'status' => $ticket->status,
            ],
            priority: 'high',
        );
    }

    private function handleClosed(TicketClosedEvent $event): void
    {
        if (! $event->assignedUserId) {
            return;
        }

        $ticket = ChatTicket::query()
            ->where('tenant_id', $event->tenantId)
            ->with('extended')
            ->find($event->ticketId);

        if (! $ticket) {
            return;
        }

        $this->dispatcher->dispatch(
            tenantId: $event->tenantId,
            userIds: [$event->assignedUserId],
            type: 'ticket_closed',
            title: 'Ticket finalizado',
            body: $ticket->subject ?: 'O ticket foi encerrado.',
            data: [
                'ticket_id' => (string) $ticket->id,
                'status' => $ticket->status,
                'close_reason' => $ticket->close_reason,
            ],
            priority: 'normal',
        );
    }
}
