<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketEvaluation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatTicketEvaluationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_list_and_create_ticket_evaluations(): void
    {
        $user = AuthUser::factory()->create();
        $this->grantTicketPermissions($user);
        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create();

        ChatTicketEvaluation::query()->create([
            'tenant_id' => $user->tenant_id,
            'ticket_id' => $ticket->id,
            'token' => (string) \Illuminate\Support\Str::orderedUuid(),
            'rating' => 0,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticket->id}/evaluations")
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/evaluations", [
                'rating' => 5,
                'comment' => 'Ótimo',
            ])
            ->assertCreated()
            ->assertJsonFragment(['rating' => 5]);
    }

    private function grantTicketPermissions(AuthUser $user): void
    {
        $permView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $permUpdate = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.update', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user->givePermissionTo($permView, $permUpdate);
    }
}
