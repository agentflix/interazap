<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatAuditTicketTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_audit_on_ticket_and_message(): void
    {
        $user = AuthUser::factory()->create();
        $perms = [
            'chat.tickets.create',
            'chat.tickets.view',
            'chat.tickets.update',
            'chat.messages.create',
        ];
        foreach ($perms as $perm) {
            $p = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum'], ['id' => Str::orderedUuid()]);
            $user->givePermissionTo($p);
        }
        $this->be($user, 'sanctum');

        $res = $this->postJson('/api/chat/tickets', [
            'channel' => 'whatsapp',
            'remote_jid' => '5511999999999@wa',
            'subject' => 'Audit ticket',
            'priority' => 'normal',
        ])->assertCreated();

        $ticketId = $res->json('data.id');
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'chat.tickets.created',
            'auditable_id' => $ticketId,
        ]);

        // Add message
        $this->postJson("/api/chat/tickets/{$ticketId}/messages", [
            'content' => 'Olá',
            'direction' => 'outgoing',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'chat.messages.created',
        ]);

        // Close ticket
        $this->postJson("/api/chat/tickets/{$ticketId}/close", [
            'reason' => 'done',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'chat.tickets.closed',
            'auditable_id' => $ticketId,
        ]);
    }
}
