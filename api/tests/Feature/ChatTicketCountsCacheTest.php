<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Actions\ChatTicketActions;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for the chat ticket counts cache layer.
 *
 * Validates that counts() uses Redis caching with a 15-second TTL
 * and that ticket mutations correctly invalidate the cache.
 */
class ChatTicketCountsCacheTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = AuthUser::factory()->create();
        $this->tenantId = (string) $this->user->tenant_id;

        $permView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'],
            ['id' => Str::orderedUuid()]
        );
        $permCreate = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.create', 'guard_name' => 'sanctum'],
            ['id' => Str::orderedUuid()]
        );
        $permUpdate = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.update', 'guard_name' => 'sanctum'],
            ['id' => Str::orderedUuid()]
        );
        $this->user->givePermissionTo($permView);
        $this->user->givePermissionTo($permCreate);
        $this->user->givePermissionTo($permUpdate);

        $this->be($this->user, 'sanctum');
    }

    public function test_counts_are_cached_on_second_call(): void
    {
        Cache::forget("chat_counts:{$this->tenantId}");

        ChatTicket::factory()->forTenant($this->tenantId)->create(['status' => 'pending']);

        /** @var ChatTicketActions $actions */
        $actions = app(ChatTicketActions::class);

        $first = $actions->counts($this->tenantId);

        $this->assertTrue(Cache::has("chat_counts:{$this->tenantId}"));

        // Create another ticket — the cache should still return the old count
        ChatTicket::factory()->forTenant($this->tenantId)->create(['status' => 'pending']);

        $second = $actions->counts($this->tenantId);

        $this->assertSame($first, $second, 'Second call should return cached result, not fresh DB query.');
    }

    public function test_cache_is_invalidated_on_ticket_create(): void
    {
        Cache::forget("chat_counts:{$this->tenantId}");

        /** @var ChatTicketActions $actions */
        $actions = app(ChatTicketActions::class);

        // Prime the cache
        $actions->counts($this->tenantId);
        $this->assertTrue(Cache::has("chat_counts:{$this->tenantId}"));

        // Create ticket via API (triggers Cache::forget)
        $this->postJson('/api/chat/tickets', [
            'channel' => 'whatsapp',
            'remote_jid' => '5511999999999@wa',
            'subject' => 'Cache test',
            'priority' => 'normal',
        ])->assertCreated();

        $this->assertFalse(Cache::has("chat_counts:{$this->tenantId}"), 'Cache should be invalidated after ticket creation.');
    }

    public function test_cache_is_invalidated_on_ticket_close(): void
    {
        Cache::forget("chat_counts:{$this->tenantId}");

        $ticket = ChatTicket::factory()->forTenant($this->tenantId)->create(['status' => 'open']);

        /** @var ChatTicketActions $actions */
        $actions = app(ChatTicketActions::class);

        // Prime the cache
        $actions->counts($this->tenantId);
        $this->assertTrue(Cache::has("chat_counts:{$this->tenantId}"));

        // Close ticket (triggers Cache::forget in updateStatus)
        $actions->updateStatus($ticket, 'closed', 'resolved');

        $this->assertFalse(Cache::has("chat_counts:{$this->tenantId}"), 'Cache should be invalidated after ticket close.');
    }

    public function test_cache_is_invalidated_on_ticket_open(): void
    {
        Cache::forget("chat_counts:{$this->tenantId}");

        $ticket = ChatTicket::factory()->forTenant($this->tenantId)->create(['status' => 'pending']);

        /** @var ChatTicketActions $actions */
        $actions = app(ChatTicketActions::class);

        // Prime the cache
        $actions->counts($this->tenantId);
        $this->assertTrue(Cache::has("chat_counts:{$this->tenantId}"));

        // Open ticket (triggers Cache::forget in open)
        $actions->open($this->tenantId, (string) $ticket->id, (string) $this->user->id);

        $this->assertFalse(Cache::has("chat_counts:{$this->tenantId}"), 'Cache should be invalidated after ticket open.');
    }
}
