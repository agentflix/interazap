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

class ChatInitAndCursorPaginationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_init_endpoint_returns_tickets_counts_and_user_preferences_with_tenant_isolation(): void
    {
        $user = AuthUser::factory()->create();
        $otherUser = AuthUser::factory()->create();

        $permTicketsView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($permTicketsView);

        $tenantId = (string) $user->tenant_id;
        $otherTenantId = (string) $otherUser->tenant_id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create([
            'status' => 'open',
        ]);
        ChatTicket::factory()->forTenant($otherTenantId)->create([
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/chat/init?per_page=10')
            ->assertOk();

        $response
            ->assertJsonPath('data.tickets.data.0.id', $ticket->id)
            ->assertJsonPath('data.counts.open', 1)
            ->assertJsonPath('data.user_preferences.user_id', (string) $user->id)
            ->assertJsonStructure([
                'data' => [
                    'tickets' => [
                        'data',
                        'meta' => ['current_page', 'last_page', 'per_page', 'total', 'has_more'],
                        'links' => ['first', 'last', 'prev', 'next'],
                    ],
                    'counts' => ['all', 'pending', 'open', 'in_progress', 'closed'],
                    'user_preferences',
                ],
            ]);

        $this->assertSame(1, count($response->json('data.tickets.data')));
    }

    public function test_messages_cursor_pagination_supports_before_and_after(): void
    {
        $user = AuthUser::factory()->create();
        $tenantId = (string) $user->tenant_id;

        $permTicketsView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $permMessagesView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.messages.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($permTicketsView, $permMessagesView);

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();

        $m1 = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'content' => 'msg-1',
            'created_at' => now()->subMinutes(5),
        ]);
        $m2 = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'content' => 'msg-2',
            'created_at' => now()->subMinutes(4),
        ]);
        $m3 = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'content' => 'msg-3',
            'created_at' => now()->subMinutes(3),
        ]);
        $m4 = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'content' => 'msg-4',
            'created_at' => now()->subMinutes(2),
        ]);
        $m5 = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'content' => 'msg-5',
            'created_at' => now()->subMinute(),
        ]);

        $firstPage = $this->actingAs($user, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticket->id}/messages")
            ->assertOk();

        $firstPage->assertJsonPath('data.messages.0.id', $m5->id);
        $firstPage->assertJsonPath('data.messages.1.id', $m4->id);

        $beforeCursor = (string) $firstPage->json('data.messages.1.id');
        $olderPage = $this->actingAs($user, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticket->id}/messages?limit=2&before={$beforeCursor}")
            ->assertOk();

        $olderPage->assertJsonPath('data.messages.0.id', $m3->id);
        $olderPage->assertJsonPath('data.messages.1.id', $m2->id);
        $olderPage->assertJsonPath('data.meta.cursor_mode', true);

        $afterCursor = (string) $m3->id;
        $newerPage = $this->actingAs($user, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticket->id}/messages?limit=2&after={$afterCursor}")
            ->assertOk();

        $newerPage->assertJsonPath('data.messages.0.id', $m5->id);
        $newerPage->assertJsonPath('data.messages.1.id', $m4->id);
        $newerPage->assertJsonPath('data.meta.after', $afterCursor);

        // Sanity: oldest message still reachable with before cursor chain
        $olderBeforeCursor = (string) $olderPage->json('data.messages.1.id');
        $oldestPage = $this->actingAs($user, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticket->id}/messages?limit=2&before={$olderBeforeCursor}")
            ->assertOk();

        $oldestPage->assertJsonPath('data.messages.0.id', $m1->id);
    }

    public function test_messages_cursor_pagination_supports_since_as_resync_alias(): void
    {
        $user = AuthUser::factory()->create();
        $tenantId = (string) $user->tenant_id;

        $permTicketsView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $permMessagesView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.messages.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($permTicketsView, $permMessagesView);

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();

        $m1 = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'content' => 'msg-1',
            'created_at' => now()->subMinutes(5),
        ]);
        $m2 = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'content' => 'msg-2',
            'created_at' => now()->subMinutes(4),
        ]);
        $m3 = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'content' => 'msg-3',
            'created_at' => now()->subMinutes(3),
        ]);

        ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'content' => 'msg-4',
            'created_at' => now()->subMinutes(2),
        ]);
        ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'content' => 'msg-5',
            'created_at' => now()->subMinute(),
        ]);

        $byMessageCursor = $this->actingAs($user, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticket->id}/messages?limit=10&since={$m3->id}")
            ->assertOk();

        $byMessageCursor->assertJsonPath('data.meta.since', $m3->id);
        $this->assertSame([
            'msg-5',
            'msg-4',
        ], array_column($byMessageCursor->json('data.messages'), 'content'));

        $byTimestamp = $this->actingAs($user, 'sanctum')
            ->getJson('/api/chat/tickets/'.$ticket->id.'/messages?limit=10&since='.urlencode((string) $m2->created_at->toIso8601String()))
            ->assertOk();

        $byTimestamp->assertJsonPath('data.meta.since', $m2->created_at->toIso8601String());
        $this->assertSame([
            'msg-5',
            'msg-4',
            'msg-3',
        ], array_column($byTimestamp->json('data.messages'), 'content'));
        $this->assertSame('msg-1', $m1->content);
    }
}
