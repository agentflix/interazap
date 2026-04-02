<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketEvaluation;
use Domain\CRM\Models\CRMContact;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for FEAT-CHAT-TICKETS-MANAGER-059.
 *
 * Covers: list metrics/evaluation, search filters, forced close behavior,
 * and authorization checks for the manager tickets screen.
 *
 * @category Tests
 */
class ChatTicketManagerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Helper to create a user with common chat permissions.
     */
    private function createUserWithPermissions(array $extra = []): AuthUser
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();

        $permissions = array_merge([
            'chat.tickets.view',
            'chat.tickets.create',
            'chat.tickets.update',
        ], $extra);

        foreach ($permissions as $permName) {
            $perm = AuthPermission::query()->firstOrCreate(
                ['name' => $permName, 'guard_name' => 'sanctum'],
                ['id' => Str::orderedUuid()]
            );
            $user->givePermissionTo($perm);
        }

        return $user;
    }

    // ─── List metrics & evaluation ────────────────────────────────────────────

    public function test_list_includes_queued_at_and_duration_fields(): void
    {
        $user = $this->createUserWithPermissions();
        $this->be($user, 'sanctum');

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(30),
            'last_message_at' => now(),
        ]);

        $response = $this->getJson('/api/chat/tickets?per_page=10')->assertOk();

        $data = $response->json('data.0');
        $this->assertArrayHasKey('queued_at', $data);
        $this->assertArrayHasKey('wait_duration_seconds', $data);
        $this->assertArrayHasKey('service_duration_seconds', $data);
        $this->assertIsInt($data['wait_duration_seconds']);
        $this->assertIsInt($data['service_duration_seconds']);
    }

    public function test_list_includes_closed_mode_field(): void
    {
        $user = $this->createUserWithPermissions();
        $this->be($user, 'sanctum');

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_mode' => 'forced',
            'last_message_at' => now(),
        ]);

        $response = $this->getJson('/api/chat/tickets?per_page=10&status=closed')->assertOk();

        $response->assertJsonPath('data.0.closed_mode', 'forced');
    }

    public function test_list_includes_evaluation_with_has_evaluation_flag(): void
    {
        $user = $this->createUserWithPermissions();
        $this->be($user, 'sanctum');

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'closed',
            'closed_at' => now(),
            'last_message_at' => now(),
        ]);

        ChatTicketEvaluation::query()->create([
            'tenant_id' => $user->tenant_id,
            'ticket_id' => $ticket->id,
            'token' => Str::random(64),
            'rating' => 5,
            'comment' => 'Excelente',
            'submitted_at' => now(),
        ]);

        $response = $this->getJson('/api/chat/tickets?per_page=10&status=closed')->assertOk();

        $eval = $response->json('data.0.evaluation');
        $this->assertTrue($eval['has_evaluation']);
        $this->assertEquals(5, $eval['rating']);
    }

    public function test_list_evaluation_defaults_when_no_evaluation(): void
    {
        $user = $this->createUserWithPermissions();
        $this->be($user, 'sanctum');

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'in_progress',
            'last_message_at' => now(),
        ]);

        $response = $this->getJson('/api/chat/tickets?per_page=10')->assertOk();

        $eval = $response->json('data.0.evaluation');
        $this->assertNotNull($eval);
        $this->assertFalse($eval['has_evaluation']);
    }

    // ─── Search filters ────────────────────────────────────────────────────────

    public function test_search_by_protocol(): void
    {
        $user = $this->createUserWithPermissions();
        $this->be($user, 'sanctum');

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'protocol' => 'PROTO-12345',
            'status' => 'in_progress',
            'last_message_at' => now(),
        ]);

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'protocol' => 'PROTO-99999',
            'status' => 'in_progress',
            'last_message_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/chat/tickets?search=PROTO-12345&per_page=10')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.protocol', 'PROTO-12345');
    }

    public function test_search_by_contact_name(): void
    {
        $user = $this->createUserWithPermissions();
        $this->be($user, 'sanctum');

        $contact = CRMContact::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Maria Silva',
            'phone' => '5511999998888',
        ]);

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'contact_id' => $contact->id,
            'status' => 'in_progress',
            'last_message_at' => now(),
        ]);

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'in_progress',
            'last_message_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/chat/tickets?search=Maria&per_page=10')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.contact.name', 'Maria Silva');
    }

    public function test_search_by_agent_name(): void
    {
        $user = $this->createUserWithPermissions();
        $this->be($user, 'sanctum');

        $agent = AuthUser::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Agente Especialista',
        ]);

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'assigned_to' => $agent->id,
            'status' => 'in_progress',
            'last_message_at' => now(),
        ]);

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'in_progress',
            'last_message_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/chat/tickets?search=Especialista&per_page=10')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.assigned_user.name', 'Agente Especialista');
    }

    public function test_filter_by_agent_id(): void
    {
        $user = $this->createUserWithPermissions();
        $this->be($user, 'sanctum');

        $agent = AuthUser::factory()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Agente Filtrado',
        ]);

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'assigned_to' => $agent->id,
            'status' => 'in_progress',
            'last_message_at' => now(),
        ]);

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'in_progress',
            'last_message_at' => now()->subHour(),
        ]);

        $response = $this->getJson("/api/chat/tickets?agent_id={$agent->id}&per_page=10")->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_filter_by_date_range(): void
    {
        $user = $this->createUserWithPermissions();
        $this->be($user, 'sanctum');

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'in_progress',
            'last_message_at' => now(),
            'created_at' => now()->subDays(1),
        ]);

        ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'in_progress',
            'last_message_at' => now()->subDays(10),
            'created_at' => now()->subDays(10),
        ]);

        $from = now()->subDays(2)->toDateString();
        $to = now()->toDateString();

        $response = $this->getJson("/api/chat/tickets?from={$from}&to={$to}&per_page=10")->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    // ─── Forced close ─────────────────────────────────────────────────────────

    public function test_close_with_forced_mode_sets_closed_mode(): void
    {
        $user = $this->createUserWithPermissions(['chat.tickets.force_close']);
        $this->be($user, 'sanctum');

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(30),
        ]);

        $response = $this->postJson("/api/chat/tickets/{$ticket->id}/close", [
            'mode' => 'forced',
            'reason' => 'manager_manual_closure',
        ])->assertOk();

        $response->assertJsonPath('data.closed_mode', 'forced');
        $response->assertJsonPath('data.close_reason', 'manager_manual_closure');
        $response->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('chat_tickets', [
            'id' => $ticket->id,
            'status' => 'closed',
            'closed_mode' => 'forced',
        ]);

        $this->assertDatabaseHas('chat_tickets_extended', [
            'ticket_id' => $ticket->id,
            'closed_by' => $user->id,
        ]);
    }

    public function test_normal_close_still_works(): void
    {
        $user = $this->createUserWithPermissions();
        $this->be($user, 'sanctum');

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(30),
        ]);

        $response = $this->postJson("/api/chat/tickets/{$ticket->id}/close", [
            'reason' => 'resolved_normally',
        ])->assertOk();

        $response->assertJsonPath('data.closed_mode', 'normal');
        $response->assertJsonPath('data.status', 'closed');
    }

    public function test_forced_close_requires_force_close_permission(): void
    {
        // User has update but NOT force_close permission
        $user = $this->createUserWithPermissions();
        $this->be($user, 'sanctum');

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(30),
        ]);

        $this->postJson("/api/chat/tickets/{$ticket->id}/close", [
            'mode' => 'forced',
            'reason' => 'unauthorized_attempt',
        ])->assertForbidden();
    }

    public function test_forced_close_skips_evaluation_and_message(): void
    {
        $user = $this->createUserWithPermissions(['chat.tickets.force_close']);
        $this->be($user, 'sanctum');

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(30),
        ]);

        $this->postJson("/api/chat/tickets/{$ticket->id}/close", [
            'mode' => 'forced',
            'reason' => 'silent_close',
        ])->assertOk();

        // No evaluation should be created for forced close
        $this->assertDatabaseMissing('chat_ticket_evaluations', [
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_close_records_closed_by_user_id(): void
    {
        $user = $this->createUserWithPermissions(['chat.tickets.force_close']);
        $this->be($user, 'sanctum');

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(30),
        ]);

        $this->postJson("/api/chat/tickets/{$ticket->id}/close", [
            'mode' => 'forced',
            'reason' => 'manager_action',
        ])->assertOk();

        $this->assertDatabaseHas('chat_tickets_extended', [
            'ticket_id' => $ticket->id,
            'closed_by' => $user->id,
        ]);
    }

    // ─── Tenant isolation ─────────────────────────────────────────────────────

    public function test_cannot_view_tickets_from_other_tenant(): void
    {
        $user = $this->createUserWithPermissions();
        $this->be($user, 'sanctum');

        $otherUser = AuthUser::factory()->create();
        ChatTicket::factory()->forTenant($otherUser->tenant_id)->create([
            'status' => 'in_progress',
            'last_message_at' => now(),
        ]);

        $response = $this->getJson('/api/chat/tickets?per_page=10')->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    public function test_cannot_force_close_ticket_from_other_tenant(): void
    {
        $user = $this->createUserWithPermissions(['chat.tickets.force_close']);
        $this->be($user, 'sanctum');

        $otherUser = AuthUser::factory()->create();
        $ticket = ChatTicket::factory()->forTenant($otherUser->tenant_id)->create([
            'status' => 'in_progress',
        ]);

        $this->postJson("/api/chat/tickets/{$ticket->id}/close", [
            'mode' => 'forced',
            'reason' => 'cross_tenant_attempt',
        ])->assertForbidden();
    }
}
