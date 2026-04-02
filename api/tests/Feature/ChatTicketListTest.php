<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatTicketListTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_list_returns_contact_last_message_and_unread_count(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $perm = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'], ['id' => Str::orderedUuid()]);
        $user->givePermissionTo($perm);
        $this->be($user, 'sanctum');

        $contact = CRMContact::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Contato Teste',
            'phone' => '55999999999',
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'contact_id' => $contact->id,
            'status' => 'in_progress',
            'last_message_at' => now()->subMinutes(10),
        ]);

        ChatMessage::query()->create([
            'tenant_id' => $user->tenant_id,
            'ticket_id' => $ticket->id,
            'direction' => 'incoming',
            'content' => 'Preciso de ajuda',
            'is_from_contact' => true,
            'status' => 'received',
            'created_at' => now()->subMinutes(5),
        ]);

        ChatMessage::query()->create([
            'tenant_id' => $user->tenant_id,
            'ticket_id' => $ticket->id,
            'direction' => 'outgoing',
            'content' => 'Claro, vamos lá',
            'is_from_contact' => false,
            'status' => 'sent',
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $ticket->update(['last_message_at' => now()]);

        $response = $this->getJson('/api/chat/tickets?per_page=10')->assertOk();

        $response->assertJsonPath('data.0.contact.name', 'Contato Teste');
        $response->assertJsonPath('data.0.last_message.content', 'Claro, vamos lá');
        $response->assertJsonPath('data.0.unread_count', 1);
        $response->assertJsonPath('data.0.last_message.user.id', $user->id);
    }

    public function test_list_counts_merge_open_and_in_progress(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $perm = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'], ['id' => Str::orderedUuid()]);
        $user->givePermissionTo($perm);
        $this->be($user, 'sanctum');

        ChatTicket::factory()->forTenant($user->tenant_id)->create(['status' => 'open']);
        ChatTicket::factory()->forTenant($user->tenant_id)->create(['status' => 'in_progress']);

        $this->getJson('/api/chat/tickets?per_page=10')
            ->assertOk()
            ->assertJsonPath('counts.open', 2)
            ->assertJsonPath('counts.pending', 0);
    }

    public function test_list_includes_assigned_user_payload(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $perm = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'], ['id' => Str::orderedUuid()]);
        $user->givePermissionTo($perm);
        $this->be($user, 'sanctum');

        $assignedUser = AuthUser::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Agente Teste',
        ]);

        $contact = CRMContact::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Contato Atribuído',
        ]);

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'contact_id' => $contact->id,
            'assigned_to' => $assignedUser->id,
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/chat/tickets?per_page=10')->assertOk();

        $response->assertJsonPath('data.0.assigned_user.id', $assignedUser->id);
        $response->assertJsonPath('data.0.assigned_user.name', 'Agente Teste');
    }

    public function test_list_filters_by_sentiment_and_sorts_by_sentiment_score(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $perm = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'], ['id' => Str::orderedUuid()]);
        $user->givePermissionTo($perm);
        $this->be($user, 'sanctum');

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'open',
            'remote_jid' => '5511911111111@wa',
            'sentiment' => 'critical',
            'sentiment_score' => 95,
        ]);

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'open',
            'remote_jid' => '5511922222222@wa',
            'sentiment' => 'critical',
            'sentiment_score' => 82,
        ]);

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'open',
            'remote_jid' => '5511933333333@wa',
            'sentiment' => 'neutral',
            'sentiment_score' => 50,
        ]);

        $response = $this->getJson('/api/chat/tickets?sentiment=critical&sort_by=sentiment_score&per_page=10')
            ->assertOk();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.sentiment', 'critical');
        $response->assertJsonPath('data.1.sentiment', 'critical');
        $response->assertJsonPath('data.0.sentiment_score', 95);
        $response->assertJsonPath('data.1.sentiment_score', 82);
    }

    public function test_list_resource_includes_sentiment_fields(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $perm = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'], ['id' => Str::orderedUuid()]);
        $user->givePermissionTo($perm);
        $this->be($user, 'sanctum');

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'remote_jid' => '5511944444444@wa',
            'sentiment' => 'negative',
            'sentiment_score' => 71,
            'sentiment_updated_at' => now(),
        ]);

        $this->getJson('/api/chat/tickets?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ticket->id)
            ->assertJsonPath('data.0.sentiment', 'negative')
            ->assertJsonPath('data.0.sentiment_score', 71)
            ->assertJsonPath('data.0.sentiment_updated_at', $ticket->sentiment_updated_at?->toAtomString());
    }

    public function test_list_returns_only_latest_ticket_per_contact(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $perm = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'], ['id' => Str::orderedUuid()]);
        $user->givePermissionTo($perm);
        $this->be($user, 'sanctum');

        $contact = CRMContact::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Rafael Amor',
            'phone' => '55999888777',
        ]);

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'contact_id' => $contact->id,
            'status' => 'closed',
            'last_message_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        $latestTicket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'contact_id' => $contact->id,
            'status' => 'closed',
            'last_message_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $response = $this->getJson('/api/chat/tickets?status=closed&per_page=10')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $latestTicket->id);
        $response->assertJsonPath('data.0.contact.name', 'Rafael Amor');
    }
}
