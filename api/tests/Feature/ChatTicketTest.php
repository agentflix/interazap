<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatTicketTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_create_and_list_ticket(): void
    {
        $user = AuthUser::factory()->create();

        $permCreate = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.tickets.create', 'guard_name' => 'sanctum'], ['id' => \Illuminate\Support\Str::orderedUuid()]);
        $permView = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'], ['id' => \Illuminate\Support\Str::orderedUuid()]);
        $permUpdate = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.tickets.update', 'guard_name' => 'sanctum'], ['id' => \Illuminate\Support\Str::orderedUuid()]);
        $user->givePermissionTo($permCreate);
        $user->givePermissionTo($permView);
        $user->givePermissionTo($permUpdate);

        $this->be($user, 'sanctum');
        // $this->withoutMiddleware(\Illuminate\Auth\Middleware\Authorize::class); // Authorize is manually called

        $res = $this->postJson('/api/chat/tickets', [
            'channel' => 'whatsapp',
            'remote_jid' => '5511999999999@wa',
            'subject' => 'Suporte',
            'priority' => 'normal',
        ])->assertCreated();

        $this->assertNotNull($res->json('data.id'));
        $ticketId = $res->json('data.id');

        $this->getJson('/api/chat/tickets')
            ->assertOk()
            ->assertJsonFragment(['id' => $ticketId]);
    }

    public function test_add_message_to_ticket(): void
    {
        $user = AuthUser::factory()->create();

        $permCreate = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.messages.create', 'guard_name' => 'sanctum'], ['id' => \Illuminate\Support\Str::orderedUuid()]);
        $user->givePermissionTo($permCreate);

        $this->be($user, 'sanctum');
        // $this->withoutMiddleware(\Illuminate\Auth\Middleware\Authorize::class);
        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create();

        $this->postJson("/api/chat/tickets/{$ticket->id}/messages", [
            'content' => 'Olá',
            'direction' => 'incoming',
        ])->assertCreated()
            ->assertJsonFragment(['content' => 'Olá']);
    }
}
