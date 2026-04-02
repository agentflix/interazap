<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatTicketControllerExtraTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  array<int, string>  $permissions
     */
    private function grantPermissions(AuthUser $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => Str::orderedUuid()]
            );
            $user->givePermissionTo($perm);
        }
    }

    public function test_counts_open_close_and_read_endpoints(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantPermissions($user, [
            'chat.tickets.view',
            'chat.tickets.update',
            'chat.tickets.create',
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'pending',
        ]);

        $incomingMessage = ChatMessage::factory()->create([
            'tenant_id' => $user->tenant_id,
            'ticket_id' => $ticket->id,
            'direction' => 'incoming',
            'status' => 'received',
            'read_at' => null,
        ]);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/chat/tickets?per_page=10')
            ->assertOk()
            ->assertJsonStructure(['data', 'counts']);

        $this->postJson('/api/chat/tickets/'.$ticket->id.'/open')
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->postJson('/api/chat/tickets/'.$ticket->id.'/close', [
            'reason' => 'resolved',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->postJson('/api/chat/tickets/'.$ticket->id.'/read')
            ->assertOk();

        $this->assertNotNull($incomingMessage->fresh()->read_at);
    }

    public function test_open_endpoint_requires_update_permission(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantPermissions($user, ['chat.tickets.view']);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/chat/tickets/'.$ticket->id.'/open')
            ->assertForbidden();
    }

    public function test_close_endpoint_requires_update_permission(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantPermissions($user, ['chat.tickets.view']);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/chat/tickets/'.$ticket->id.'/close', [
            'reason' => 'resolved',
        ])->assertForbidden();
    }

    public function test_forced_close_requires_force_close_permission(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantPermissions($user, [
            'chat.tickets.view',
            'chat.tickets.update',
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/chat/tickets/'.$ticket->id.'/close', [
            'reason' => 'resolved',
            'mode' => 'forced',
        ])->assertForbidden();
    }
}
