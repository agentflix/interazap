<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Actions\ChatTicketTransferActions;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketTransfer;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatTicketTransferActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_transfer_updates_ticket_and_creates_history(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $fromUser = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $toUser = AuthUser::factory()->create(['tenant_id' => $tenant->id]);

        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'assigned_to' => $fromUser->id,
        ]);

        $actions = app(ChatTicketTransferActions::class);
        $transfer = $actions->transfer($ticket, (string) $toUser->id, 'Reassign');

        $this->assertInstanceOf(ChatTicketTransfer::class, $transfer);
        $this->assertSame((string) $toUser->id, $ticket->fresh()->assigned_to);
    }

    public function test_list_returns_transfers_for_ticket(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create();
        $fromUser = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $toUser = AuthUser::factory()->create(['tenant_id' => $tenant->id]);

        ChatTicketTransfer::query()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'status' => 'completed',
        ]);

        $actions = app(ChatTicketTransferActions::class);
        $paginator = $actions->list((string) $ticket->id);

        $this->assertSame(1, $paginator->total());
    }

    public function test_transfer_creates_internal_note_with_reason(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $fromUser = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $toUser = AuthUser::factory()->create(['tenant_id' => $tenant->id]);

        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'assigned_to' => $fromUser->id,
        ]);

        $actions = app(ChatTicketTransferActions::class);
        $transfer = $actions->transfer($ticket, (string) $toUser->id, 'Repasse com contexto interno');

        $this->assertDatabaseHas('chat_messages', [
            'tenant_id' => (string) $tenant->id,
            'ticket_id' => (string) $ticket->id,
            'type' => 'internal_note',
            'content' => 'Repasse com contexto interno',
            'source' => 'system',
            'user_id' => (string) $fromUser->id,
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'tenant_id' => (string) $tenant->id,
            'ticket_id' => (string) $ticket->id,
            'type' => 'internal_note',
            'metadata->transfer_id' => (string) $transfer->id,
            'metadata->from_user_id' => (string) $fromUser->id,
            'metadata->to_user_id' => (string) $toUser->id,
        ]);
    }
}
