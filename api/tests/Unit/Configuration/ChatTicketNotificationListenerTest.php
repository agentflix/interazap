<?php

declare(strict_types=1);

namespace Tests\Unit\Configuration;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\Configuration\Events\TicketAssignedEvent;
use Domain\Configuration\Events\TicketClosedEvent;
use Domain\Configuration\Events\TicketCreatedEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ChatTicketNotificationListenerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_notification_on_ticket_created_event(): void
    {
        Queue::fake();

        $user = AuthUser::factory()->create();
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $user->tenant_id,
            'assigned_to' => $user->id,
            'subject' => 'Atendimento novo',
        ]);

        Event::dispatch(new TicketCreatedEvent((string) $ticket->tenant_id, (string) $ticket->id));

        $this->assertDatabaseHas('configuration_notifications', [
            'tenant_id' => $ticket->tenant_id,
            'type' => 'new_ticket',
        ]);
    }

    public function test_creates_notification_on_ticket_assigned_and_closed_events(): void
    {
        Queue::fake();

        $user = AuthUser::factory()->create();
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $user->tenant_id,
            'assigned_to' => $user->id,
            'subject' => 'Ticket XYZ',
            'status' => 'open',
        ]);

        Event::dispatch(new TicketAssignedEvent((string) $ticket->tenant_id, (string) $ticket->id, (string) $user->id));
        Event::dispatch(new TicketClosedEvent((string) $ticket->tenant_id, (string) $ticket->id, (string) $user->id));

        $this->assertDatabaseHas('configuration_notifications', [
            'tenant_id' => $ticket->tenant_id,
            'user_id' => $user->id,
            'type' => 'ticket_assigned',
        ]);

        $this->assertDatabaseHas('configuration_notifications', [
            'tenant_id' => $ticket->tenant_id,
            'user_id' => $user->id,
            'type' => 'ticket_closed',
        ]);
    }
}
