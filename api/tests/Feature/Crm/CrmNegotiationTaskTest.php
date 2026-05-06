<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMNegotiationTask;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);

    $funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenant->id]);
    $step = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $funnel->id,
    ]);

    $this->negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $funnel->id,
        'crm_negotiation_funnel_step_id' => $step->id,
    ]);
});

test('can create negotiation task', function (): void {
    $payload = [
        'title' => 'Follow-up',
        'description' => 'Call the client',
        'due_date' => now()->addDays(2)->toDateString(),
        'status' => 'pending',
        'auth_user_id' => $this->user->id,
    ];

    $response = $this->postJson("/api/crm/negotiations/{$this->negotiation->id}/tasks", $payload);

    $response->assertCreated();

    $this->assertDatabaseHas('crm_negotiation_tasks', [
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'title' => 'Follow-up',
        'status' => 'pending',
    ]);
});

test('can list tasks assigned to authenticated user', function (): void {
    CRMNegotiationTask::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'auth_user_id' => $this->user->id,
        'title' => 'Assigned task',
    ]);

    $response = $this->getJson('/api/crm/negotiations/tasks/user');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Tarefas do usuário')
        ->assertJsonCount(1, 'data.tasks')
        ->assertJsonPath('data.tasks.0.title', 'Assigned task');
});

test('can list tasks for a negotiation', function (): void {
    CRMNegotiationTask::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'title' => 'Task 1',
    ]);

    CRMNegotiationTask::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'title' => 'Task 2',
    ]);

    $response = $this->getJson("/api/crm/negotiations/{$this->negotiation->id}/tasks");

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data.tasks');
});

test('can update negotiation task', function (): void {
    $task = CRMNegotiationTask::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'title' => 'Original title',
    ]);

    $payload = [
        'title' => 'Updated title',
        'description' => 'Updated description',
        'status' => 'in_progress',
        'action_type' => 'call',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'priority' => 'high',
    ];

    $response = $this->putJson("/api/crm/negotiations/{$this->negotiation->id}/tasks/{$task->id}", $payload);

    $response
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated title')
        ->assertJsonPath('data.action_type', 'call')
        ->assertJsonPath('data.priority', 'high');

    $this->assertDatabaseHas('crm_negotiation_tasks', [
        'id' => $task->id,
        'title' => 'Updated title',
        'action_type' => 'call',
        'priority' => 'high',
    ]);
});

test('can toggle negotiation task completion', function (): void {
    $task = CRMNegotiationTask::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'title' => 'Task to toggle',
        'is_completed' => false,
        'status' => 'pending',
    ]);

    $response = $this->patchJson("/api/crm/negotiations/{$this->negotiation->id}/tasks/{$task->id}/toggle");

    $response
        ->assertOk()
        ->assertJsonPath('data.is_completed', true)
        ->assertJsonPath('data.status', 'done');

    $this->assertDatabaseHas('crm_negotiation_tasks', [
        'id' => $task->id,
        'is_completed' => true,
        'status' => 'done',
    ]);

    // Toggle back
    $response = $this->patchJson("/api/crm/negotiations/{$this->negotiation->id}/tasks/{$task->id}/toggle");

    $response
        ->assertOk()
        ->assertJsonPath('data.is_completed', false)
        ->assertJsonPath('data.status', 'pending');
});

test('can delete negotiation task', function (): void {
    $task = CRMNegotiationTask::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'title' => 'Task to delete',
    ]);

    $response = $this->deleteJson("/api/crm/negotiations/{$this->negotiation->id}/tasks/{$task->id}");

    $response->assertNoContent();

    $this->assertDatabaseMissing('crm_negotiation_tasks', [
        'id' => $task->id,
    ]);
});

test('task creation accepts all new fields', function (): void {
    $payload = [
        'title' => 'Complete task',
        'description' => 'Full description',
        'due_date' => now()->addDays(3)->toDateString(),
        'status' => 'pending',
        'action_type' => 'meeting',
        'start_time' => '14:00',
        'end_time' => '15:00',
        'priority' => 'high',
        'add_to_agenda' => true,
        'notify_ui' => true,
        'notify_email' => true,
        'notify_push' => false,
        'notify_whatsapp' => false,
    ];

    $response = $this->postJson("/api/crm/negotiations/{$this->negotiation->id}/tasks", $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('data.action_type', 'meeting')
        ->assertJsonPath('data.start_time', '14:00')
        ->assertJsonPath('data.end_time', '15:00')
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.add_to_agenda', true)
        ->assertJsonPath('data.notify_ui', true)
        ->assertJsonPath('data.notify_email', true);
});

test('cannot access tasks from another tenant', function (): void {
    $otherTenant = PlatformTenant::factory()->create();
    $otherNegotiation = CRMNegotiation::factory()->create(['tenant_id' => $otherTenant->id]);

    $task = CRMNegotiationTask::factory()->create([
        'tenant_id' => $otherTenant->id,
        'crm_negotiation_id' => $otherNegotiation->id,
        'title' => 'Other tenant task',
    ]);

    // Returns 404 because negotiation is not found for this tenant
    $this->getJson("/api/crm/negotiations/{$otherNegotiation->id}/tasks")
        ->assertStatus(404);

    $this->putJson("/api/crm/negotiations/{$otherNegotiation->id}/tasks/{$task->id}", ['title' => 'Hacked'])
        ->assertStatus(404);

    $this->deleteJson("/api/crm/negotiations/{$otherNegotiation->id}/tasks/{$task->id}")
        ->assertStatus(404);
});
