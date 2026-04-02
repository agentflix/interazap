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
