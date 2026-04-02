<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTransmissionList;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Multi-tenancy isolation tests for Chat domain.
 *
 * Validates that tenants cannot access each other's:
 * - Tickets
 * - Messages
 * - Instances
 * - Transmission Lists
 */
class ChatMultiTenancyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $userA;

    private AuthUser $userB;

    private PlatformTenant $tenantA;

    private PlatformTenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = PlatformTenant::factory()->create();
        $this->tenantB = PlatformTenant::factory()->create();

        $this->userA = AuthUser::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->userB = AuthUser::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);

        $permissions = [
            'chat.tickets.view',
            'chat.tickets.create',
            'chat.tickets.update',
            'chat.tickets.delete',
            'chat.messages.view',
            'chat.messages.create',
            'chat.transmission_lists.view',
            'chat.transmission_lists.create',
            'chat.transmission_lists.update',
            'chat.transmission_lists.delete',
            'channels.whatsapp.view',
            'channels.whatsapp.manage',
        ];

        foreach ($permissions as $permission) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::orderedUuid()]
            );
            $this->userA->givePermissionTo($perm);
            $this->userB->givePermissionTo($perm);
        }
    }

    public function test_user_cannot_see_other_tenant_tickets(): void
    {
        $ticketA = ChatTicket::factory()->forTenant($this->tenantA->id)->create();
        $ticketB = ChatTicket::factory()->forTenant($this->tenantB->id)->create();

        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/chat/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ticketA->id);

        $this->actingAs($this->userB, 'sanctum')
            ->getJson('/api/chat/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ticketB->id);
    }

    public function test_user_cannot_access_other_tenant_ticket_by_id(): void
    {
        $ticketB = ChatTicket::factory()->forTenant($this->tenantB->id)->create();

        $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticketB->id}")
            ->assertNotFound();
    }

    public function test_user_cannot_open_other_tenant_ticket(): void
    {
        $ticketB = ChatTicket::factory()->forTenant($this->tenantB->id)->create([
            'status' => 'pending',
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticketB->id}/open")
            ->assertNotFound();

        $ticketB->refresh();
        $this->assertNotEquals('open', $ticketB->status);
    }

    public function test_user_cannot_close_other_tenant_ticket(): void
    {
        $ticketB = ChatTicket::factory()->forTenant($this->tenantB->id)->create([
            'status' => 'open',
        ]);

        // Returns 403 Forbidden because the FormRequest blocks cross-tenant access
        // This is correct security behavior - preventing information disclosure via 404
        $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticketB->id}/close", [
                'reason' => 'spam',
            ])
            ->assertForbidden();

        $ticketB->refresh();
        $this->assertNotEquals('closed', $ticketB->status);
    }

    public function test_user_cannot_see_other_tenant_messages(): void
    {
        $ticketA = ChatTicket::factory()->forTenant($this->tenantA->id)->create();
        $ticketB = ChatTicket::factory()->forTenant($this->tenantB->id)->create();

        ChatMessage::query()->create([
            'tenant_id' => $this->tenantA->id,
            'ticket_id' => $ticketA->id,
            'content' => 'Message A',
            'type' => 'text',
            'direction' => 'incoming',
            'status' => 'received',
        ]);

        ChatMessage::query()->create([
            'tenant_id' => $this->tenantB->id,
            'ticket_id' => $ticketB->id,
            'content' => 'Message B',
            'type' => 'text',
            'direction' => 'incoming',
            'status' => 'received',
        ]);

        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticketA->id}/messages")
            ->assertOk();

        // API returns messages under data.messages
        $messages = collect($response->json('data.messages'));
        $this->assertTrue($messages->contains('content', 'Message A'));
        $this->assertFalse($messages->contains('content', 'Message B'));

        $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticketB->id}/messages")
            ->assertNotFound();
    }

    public function test_user_cannot_send_message_to_other_tenant_ticket(): void
    {
        $ticketB = ChatTicket::factory()->forTenant($this->tenantB->id)->create();

        $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticketB->id}/messages", [
                'content' => 'Malicious message',
                'direction' => 'outgoing',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('chat_messages', [
            'content' => 'Malicious message',
        ]);
    }

    public function test_user_cannot_see_other_tenant_transmission_lists(): void
    {
        ChatTransmissionList::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Transmission List A',
        ]);

        ChatTransmissionList::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Transmission List B',
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/chat/transmission-lists')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Transmission List A');

        $this->actingAs($this->userB, 'sanctum')
            ->getJson('/api/chat/transmission-lists')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Transmission List B');
    }

    public function test_user_cannot_update_other_tenant_transmission_list(): void
    {
        $transmissionListB = ChatTransmissionList::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Transmission List B',
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->putJson("/api/chat/transmission-lists/{$transmissionListB->id}", [
                'name' => 'Hacked Transmission List',
            ])
            ->assertNotFound();

        $transmissionListB->refresh();
        $this->assertEquals('Transmission List B', $transmissionListB->name);
    }

    public function test_user_cannot_delete_other_tenant_transmission_list(): void
    {
        $transmissionListB = ChatTransmissionList::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->deleteJson("/api/chat/transmission-lists/{$transmissionListB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('chat_transmission_lists', [
            'id' => $transmissionListB->id,
        ]);
    }

    public function test_user_cannot_see_other_tenant_instances(): void
    {
        ChatInstance::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Instance A',
        ]);

        ChatInstance::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Instance B',
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/channels')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Instance A');
    }

    public function test_user_cannot_access_other_tenant_instance_by_id(): void
    {
        $instanceB = ChatInstance::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/channels/{$instanceB->id}")
            ->assertNotFound();
    }

    public function test_user_cannot_delete_other_tenant_instance(): void
    {
        $instanceB = ChatInstance::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);

        $this->actingAs($this->userA, 'sanctum')
            ->deleteJson("/api/channels/{$instanceB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('chat_instances', [
            'id' => $instanceB->id,
        ]);
    }

    public function test_ticket_creation_enforces_tenant_isolation(): void
    {
        $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/chat/tickets', [
                'channel' => 'whatsapp',
                'remote_jid' => '5511999999999@wa',
                'subject' => 'Test',
            ])
            ->assertCreated();

        $ticket = ChatTicket::query()->latest()->first();
        $this->assertEquals((string) $this->tenantA->id, $ticket->tenant_id);
    }

    public function test_transmission_list_creation_enforces_tenant_isolation(): void
    {
        $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/chat/transmission-lists', [
                'name' => 'My Transmission List',
                'message' => 'Hello {{name}}',
                'status' => 'draft',
            ])
            ->assertCreated();

        $transmissionList = ChatTransmissionList::query()->latest()->first();
        $this->assertEquals((string) $this->tenantA->id, $transmissionList->tenant_id);
    }

    public function test_ticket_counts_only_include_own_tenant(): void
    {
        ChatTicket::factory()->count(3)->forTenant($this->tenantA->id)->create(['status' => 'open']);
        ChatTicket::factory()->count(5)->forTenant($this->tenantB->id)->create(['status' => 'open']);

        $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/chat/tickets?per_page=10')
            ->assertOk()
            ->assertJsonPath('counts.open', 3);

        $this->actingAs($this->userB, 'sanctum')
            ->getJson('/api/chat/tickets?per_page=10')
            ->assertOk()
            ->assertJsonPath('counts.open', 5);
    }

    public function test_message_count_respects_tenant_isolation(): void
    {
        $ticketA = ChatTicket::factory()->forTenant($this->tenantA->id)->create();

        for ($i = 0; $i < 5; $i++) {
            ChatMessage::query()->create([
                'tenant_id' => $this->tenantA->id,
                'ticket_id' => $ticketA->id,
                'content' => "Message A {$i}",
                'type' => 'text',
                'direction' => 'incoming',
                'status' => 'received',
            ]);
        }

        $ticketB = ChatTicket::factory()->forTenant($this->tenantB->id)->create();

        for ($i = 0; $i < 8; $i++) {
            ChatMessage::query()->create([
                'tenant_id' => $this->tenantB->id,
                'ticket_id' => $ticketB->id,
                'content' => "Message B {$i}",
                'type' => 'text',
                'direction' => 'incoming',
                'status' => 'received',
            ]);
        }

        $responseA = $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticketA->id}/messages")
            ->assertOk();

        // API returns messages under data.messages
        $messagesA = collect($responseA->json('data.messages'));
        $this->assertEquals(5, $messagesA->count());
        $this->assertTrue($messagesA->every(fn ($m): bool => str_starts_with($m['content'] ?? '', 'Message A')));

        $responseB = $this->actingAs($this->userB, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticketB->id}/messages")
            ->assertOk();

        $messagesB = collect($responseB->json('data.messages'));
        $this->assertEquals(8, $messagesB->count());
        $this->assertTrue($messagesB->every(fn ($m): bool => str_starts_with($m['content'] ?? '', 'Message B')));
    }
}
