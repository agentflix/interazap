<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Http\Resources\ChatInstanceResource;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketEvaluation;
use Domain\Configuration\Models\ConfigurationNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatTicketEvaluationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_close_ticket_with_evaluation_disabled_does_not_create_evaluation(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantTicketPermissions($user);

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'evaluation_enabled' => false,
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/close")
            ->assertOk();

        $this->assertFalse(
            ChatTicketEvaluation::query()
                ->where('ticket_id', $ticket->id)
                ->exists()
        );
    }

    public function test_close_ticket_with_evaluation_enabled_creates_evaluation_with_token(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantTicketPermissions($user);

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'evaluation_enabled' => true,
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/close")
            ->assertOk();

        $evaluation = ChatTicketEvaluation::query()->where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($evaluation);
        $this->assertNotSame('', (string) $evaluation->token);
        $this->assertNull($evaluation->submitted_at);
    }

    public function test_close_ticket_with_evaluation_enabled_persists_invitation_message(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantTicketPermissions($user);

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'provider' => 'uazapi',
            'evaluation_enabled' => true,
            'webhook_token' => 'tok-evaluation',
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'status' => 'pending',
            'phone_e164' => '5511999999999',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/close")
            ->assertOk();

        $evaluation = ChatTicketEvaluation::query()->where('ticket_id', $ticket->id)->firstOrFail();

        $message = ChatMessage::query()
            ->where('ticket_id', $ticket->id)
            ->where('direction', 'outgoing')
            ->where('type', 'text')
            ->where('content', 'like', '%/chat/evaluations/%')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($message);
        $this->assertStringContainsString('/chat/evaluations/'.$evaluation->token, (string) $message?->content);
    }

    public function test_submit_rating_below_cutoff_dispatches_evaluation_low_score_notification(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantTicketPermissions($user);

        $manager = AuthUser::factory()->create([
            'tenant_id' => $user->tenant_id,
            'is_active' => true,
        ]);

        $managerRole = AuthRole::query()->firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $manager->assignRole($managerRole);

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'evaluation_enabled' => true,
            'evaluation_cutoff_score' => 3,
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/close")
            ->assertOk();

        $evaluation = ChatTicketEvaluation::query()->where('ticket_id', $ticket->id)->firstOrFail();

        $this->postJson("/api/public/chat/evaluations/{$evaluation->token}", [
            'rating' => 2,
            'comment' => 'Atendimento ruim',
        ])->assertOk();

        $this->assertTrue(
            ConfigurationNotification::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('user_id', $manager->id)
                ->where('type', 'evaluation_low_score')
                ->exists()
        );
    }

    public function test_submit_rating_above_cutoff_does_not_dispatch_evaluation_low_score_notification(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantTicketPermissions($user);

        $admin = AuthUser::factory()->create([
            'tenant_id' => $user->tenant_id,
            'is_active' => true,
        ]);

        $adminRole = AuthRole::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $admin->assignRole($adminRole);

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'evaluation_enabled' => true,
            'evaluation_cutoff_score' => 3,
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/close")
            ->assertOk();

        $evaluation = ChatTicketEvaluation::query()->where('ticket_id', $ticket->id)->firstOrFail();

        $this->postJson("/api/public/chat/evaluations/{$evaluation->token}", [
            'rating' => 5,
            'comment' => 'Muito bom',
        ])->assertOk();

        $this->assertFalse(
            ConfigurationNotification::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('type', 'evaluation_low_score')
                ->exists()
        );
    }

    public function test_chat_instance_resource_exposes_evaluation_fields(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'evaluation_enabled' => true,
            'evaluation_cutoff_score' => 2,
        ]);

        $resource = new ChatInstanceResource($instance);
        $data = $resource->toArray(new Request);

        $this->assertArrayHasKey('evaluation_enabled', $data);
        $this->assertArrayHasKey('evaluation_cutoff_score', $data);
        $this->assertTrue((bool) $data['evaluation_enabled']);
        $this->assertSame(2, (int) $data['evaluation_cutoff_score']);
    }

    public function test_close_ticket_generates_evaluation_and_public_submission(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantTicketPermissions($user);

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'evaluation_enabled' => true,
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/close")
            ->assertOk();

        $evaluation = ChatTicketEvaluation::query()->where('ticket_id', $ticket->id)->firstOrFail();

        $this->postJson("/api/public/chat/evaluations/{$evaluation->token}", [
            'rating' => 5,
            'comment' => 'Muito bom',
        ])->assertOk();

        $evaluation->refresh();
        $this->assertEquals(5, $evaluation->rating);
        $this->assertNotNull($evaluation->submitted_at);

        // Ticket deve exibir avaliação
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonFragment(['rating' => 5, 'comment' => 'Muito bom']);
    }

    private function grantTicketPermissions(AuthUser $user): void
    {
        $permUpdate = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.update', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $permView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user->givePermissionTo($permUpdate);
        $user->givePermissionTo($permView);
    }
}
