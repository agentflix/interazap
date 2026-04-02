<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationTask;
use Domain\Platform\Models\PlatformTenant;

uses()->group('controllers', 'crm');

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user, 'sanctum');
});

test('GET /api/crm/negotiations/tasks/user returns user tasks', function (): void {
    // Arrange: create tasks for current user
    $negotiation = CRMNegotiation::factory()
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    $myTask = CRMNegotiationTask::factory()
        ->state([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_id' => $negotiation->id,
            'auth_user_id' => $this->user->id,
            'title' => 'Minha tarefa',
            'status' => 'pending',
        ])
        ->create();

    // Create task for another user
    $otherUser = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    CRMNegotiationTask::factory()
        ->state([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_id' => $negotiation->id,
            'auth_user_id' => $otherUser->id,
            'title' => 'Tarefa de outro usuário',
        ])
        ->create();

    // Act
    $response = $this->getJson('/api/crm/negotiations/tasks/user');

    // Assert
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'tasks' => [
                    '*' => [
                        'id',
                        'crm_negotiation_id',
                        'title',
                        'description',
                        'status',
                        'due_date',
                        'created_at',
                    ],
                ],
            ],
        ]);

    $tasks = $response->json('data.tasks');
    expect($tasks)->toHaveCount(1)
        ->and($tasks[0]['id'])->toBe($myTask->id)
        ->and($tasks[0]['title'])->toBe('Minha tarefa');
});

test('GET /api/crm/negotiations/tasks/user includes negotiation and company', function (): void {
    // Arrange
    $negotiation = CRMNegotiation::factory()
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    CRMNegotiationTask::factory()
        ->state([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_id' => $negotiation->id,
            'auth_user_id' => $this->user->id,
        ])
        ->create();

    // Act
    $response = $this->getJson('/api/crm/negotiations/tasks/user');

    // Assert
    $response->assertOk();

    $task = $response->json('data.tasks.0');
    expect($task)->toHaveKey('negotiation');
});

test('GET /api/crm/negotiations/tasks/user respects tenant isolation', function (): void {
    // Arrange: create task for another tenant
    $otherTenant = PlatformTenant::factory()->create();
    $otherUser = AuthUser::factory()->create(['tenant_id' => $otherTenant->id]);

    $negotiation = CRMNegotiation::factory()
        ->state(['tenant_id' => $otherTenant->id])
        ->create();

    CRMNegotiationTask::factory()
        ->state([
            'tenant_id' => $otherTenant->id,
            'crm_negotiation_id' => $negotiation->id,
            'auth_user_id' => $otherUser->id,
            'title' => 'Tarefa de outro tenant',
        ])
        ->create();

    // Act
    $response = $this->getJson('/api/crm/negotiations/tasks/user');

    // Assert: should not see tasks from other tenant
    $response->assertOk();
    expect($response->json('data.tasks'))->toBeEmpty();
});

test('GET /api/crm/negotiations/tasks/user orders by due_date', function (): void {
    // Arrange: create tasks with different due dates
    $negotiation = CRMNegotiation::factory()
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    $task1 = CRMNegotiationTask::factory()
        ->state([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_id' => $negotiation->id,
            'auth_user_id' => $this->user->id,
            'title' => 'Tarefa 1',
            'due_date' => now()->addDays(5),
        ])
        ->create();

    $task2 = CRMNegotiationTask::factory()
        ->state([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_id' => $negotiation->id,
            'auth_user_id' => $this->user->id,
            'title' => 'Tarefa 2',
            'due_date' => now()->addDays(1),
        ])
        ->create();

    $task3 = CRMNegotiationTask::factory()
        ->state([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_id' => $negotiation->id,
            'auth_user_id' => $this->user->id,
            'title' => 'Tarefa 3',
            'due_date' => now()->addDays(3),
        ])
        ->create();

    // Act
    $response = $this->getJson('/api/crm/negotiations/tasks/user');

    // Assert: should be ordered by due_date
    $response->assertOk();

    $tasks = $response->json('data.tasks');
    expect($tasks)->toHaveCount(3)
        ->and($tasks[0]['id'])->toBe($task2->id) // due in 1 day
        ->and($tasks[1]['id'])->toBe($task3->id) // due in 3 days
        ->and($tasks[2]['id'])->toBe($task1->id); // due in 5 days
});
