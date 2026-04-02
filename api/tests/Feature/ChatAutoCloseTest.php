<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatAutoCloseTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_auto_close_closes_inactive_ticket(): void
    {
        $user = AuthUser::factory()->create();
        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'pending',
            'last_message_at' => \Illuminate\Support\Facades\Date::now()->subMinutes(120),
        ]);

        config()->set('chat.auto_close_minutes', 60);
        $this->artisan('chat:auto-close')->assertSuccessful();

        $ticket->refresh();
        $this->assertEquals('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);
        $this->assertEquals('Auto-close por inatividade', $ticket->close_reason);
    }
}
