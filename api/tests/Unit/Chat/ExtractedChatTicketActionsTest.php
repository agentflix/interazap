<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Actions\ListChatTicketsAction;
use Domain\Chat\Actions\UpdateChatTicketAction;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExtractedChatTicketActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_list_action_filters_by_tenant_and_status(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        ChatTicket::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'status' => 'pending',
        ]);

        ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'closed',
        ]);

        ChatTicket::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => 'pending',
        ]);

        /** @var ListChatTicketsAction $action */
        $action = app(ListChatTicketsAction::class);
        $result = $action->list((string) $tenant->id, [
            'status' => 'pending',
            'group_by_contact' => false,
            'per_page' => 50,
        ]);

        $this->assertSame(2, $result->total());
    }

    public function test_update_action_closes_ticket_in_forced_mode(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'open',
            'instance_id' => null,
        ]);

        /** @var UpdateChatTicketAction $action */
        $action = app(UpdateChatTicketAction::class);
        $updated = $action->updateStatus($ticket, 'closed', 'encerrado por QA', 'forced');

        $this->assertSame('closed', $updated->status);
        $this->assertSame('forced', $updated->closed_mode);
        $this->assertSame('encerrado por QA', $updated->close_reason);
        $this->assertNotNull($updated->closed_at);
    }
}
