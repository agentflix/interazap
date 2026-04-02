<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketTransfer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatTicketTransferControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_transfer_requires_reason_on_dedicated_endpoint(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantTicketPermissions($user);
        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create();
        /** @var AuthUser $toUser */
        $toUser = AuthUser::factory()->create(['tenant_id' => $user->tenant_id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/transfers", [
                'to_user_id' => $toUser->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_transfer_rejects_blank_reason_on_dedicated_endpoint(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantTicketPermissions($user);
        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create();
        /** @var AuthUser $toUser */
        $toUser = AuthUser::factory()->create(['tenant_id' => $user->tenant_id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/transfers", [
                'to_user_id' => $toUser->id,
                'reason' => '   ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_transfer_requires_transfer_permission(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $permView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($permView);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create();
        /** @var AuthUser $toUser */
        $toUser = AuthUser::factory()->create(['tenant_id' => $user->tenant_id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/transfers", [
                'to_user_id' => $toUser->id,
                'reason' => 'Reassign',
            ])
            ->assertForbidden();
    }

    public function test_transfer_rejects_target_user_from_another_tenant(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantTicketPermissions($user);
        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create();
        /** @var AuthUser $toUser */
        $toUser = AuthUser::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/transfers", [
                'to_user_id' => $toUser->id,
                'reason' => 'Cross tenant should fail',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to_user_id']);
    }

    public function test_list_and_create_ticket_transfers(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantTicketPermissions($user);
        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create();
        /** @var AuthUser $toUser */
        $toUser = AuthUser::factory()->create(['tenant_id' => $user->tenant_id]);

        ChatTicketTransfer::query()->create([
            'tenant_id' => $user->tenant_id,
            'ticket_id' => $ticket->id,
            'from_user_id' => $user->id,
            'to_user_id' => $toUser->id,
            'status' => 'completed',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticket->id}/transfers")
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/transfers", [
                'to_user_id' => $toUser->id,
                'reason' => 'Reassign',
            ])
            ->assertCreated()
            ->assertJsonFragment(['to_user_id' => $toUser->id]);
    }

    private function grantTicketPermissions(AuthUser $user): void
    {
        $permView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $permTransfer = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.transfer', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user->givePermissionTo($permView, $permTransfer);
    }
}
