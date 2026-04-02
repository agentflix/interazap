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

test('PATCH /api/crm/negotiations/{id}/tasks/{taskId}/status updates task status', function (): void {
    // Arrange
    $negotiation = CRMNegotiation::factory()
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    $task = CRMNegotiationTask::factory()
        ->state([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_id' => $negotiation->id,
            'auth_user_id' => $this->user->id,
            'status' => 'pending',
        ])
        ->create();

    // Act
    $response = $this->patchJson(
        "/api/crm/negotiations/{$negotiation->id}/tasks/{$task->id}/status",
        ['status' => 'in_progress']
    );

    // Assert
    $response->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $task->refresh();
    expect($task->status->value)->toBe('in_progress');
});

test('PATCH /api/crm/negotiations/{id}/tasks/{taskId}/status includes relationships', function (): void {
    // Arrange
    $negotiation = CRMNegotiation::factory()
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    $task = CRMNegotiationTask::factory()
        ->state([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_id' => $negotiation->id,
            'auth_user_id' => $this->user->id,
            'status' => 'pending',
        ])
        ->create();

    // Act
    $response = $this->patchJson(
        "/api/crm/negotiations/{$negotiation->id}/tasks/{$task->id}/status",
        ['status' => 'done']
    );

    // Assert
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'status',
                'negotiation' => [
                    'id',
                    'title',
                ],
                'user',
            ],
        ]);
});

test('PATCH /api/crm/negotiations/{id}/tasks/{taskId}/status validates status is required', function (): void {
    // Arrange
    $negotiation = CRMNegotiation::factory()
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    $task = CRMNegotiationTask::factory()
        ->state([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_id' => $negotiation->id,
            'auth_user_id' => $this->user->id,
        ])
        ->create();

    // Act
    $response = $this->patchJson(
        "/api/crm/negotiations/{$negotiation->id}/tasks/{$task->id}/status",
        []
    );

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('PATCH /api/crm/negotiations/{id}/tasks/{taskId}/status respects tenant isolation', function (): void {
    // Arrange: create task in another tenant
    $otherTenant = PlatformTenant::factory()->create();

    $negotiation = CRMNegotiation::factory()
        ->state(['tenant_id' => $otherTenant->id])
        ->create();

    $task = CRMNegotiationTask::factory()
        ->state([
            'tenant_id' => $otherTenant->id,
            'crm_negotiation_id' => $negotiation->id,
            'status' => 'pending',
        ])
        ->create();

    // Act: try to update task from another tenant
    $response = $this->patchJson(
        "/api/crm/negotiations/{$negotiation->id}/tasks/{$task->id}/status",
        ['status' => 'done']
    );

    // Assert: should not find the task
    $response->assertNotFound();

    // Verify task was not updated
    $task->refresh();
    expect($task->status->value)->toBe('pending');
});

test('PATCH /api/crm/negotiations/{id}/tasks/{taskId}/status returns 404 for non-existent task', function (): void {
    // Arrange
    $negotiation = CRMNegotiation::factory()
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    $fakeTaskId = \Illuminate\Support\Str::uuid()->toString();

    // Act
    $response = $this->patchJson(
        "/api/crm/negotiations/{$negotiation->id}/tasks/{$fakeTaskId}/status",
        ['status' => 'done']
    );

    // Assert
    $response->assertNotFound();
});

test('PATCH /api/crm/negotiations/{id}/tasks/{taskId}/status accepts all valid statuses', function (string $status): void {
    // Arrange
    $negotiation = CRMNegotiation::factory()
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    $task = CRMNegotiationTask::factory()
        ->state([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_id' => $negotiation->id,
            'auth_user_id' => $this->user->id,
            'status' => 'pending',
        ])
        ->create();

    // Act
    $response = $this->patchJson(
        "/api/crm/negotiations/{$negotiation->id}/tasks/{$task->id}/status",
        ['status' => $status]
    );

    // Assert
    $response->assertOk();

    $task->refresh();
    expect($task->status->value)->toBe($status);
})->with(['pending', 'in_progress', 'done']);
