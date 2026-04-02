<?php

declare(strict_types=1);

use Database\Seeders\CRMNegotiationTaskSeeder;
use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationTask;
use Domain\Platform\Models\PlatformTenant;

uses()->group('seeders', 'crm');

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user, 'sanctum');
});

test('CRMNegotiationTaskSeeder creates tasks for existing negotiations', function (): void {
    // Arrange: create negotiations
    $negotiations = CRMNegotiation::factory()
        ->count(3)
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    expect(\Domain\CRM\Models\CRMNegotiationTask::query()->count())->toBe(0);

    // Act: run seeder
    $this->seed(CRMNegotiationTaskSeeder::class);

    // Assert: tasks were created
    expect(\Domain\CRM\Models\CRMNegotiationTask::query()->count())->toBeGreaterThan(0);

    foreach ($negotiations as $negotiation) {
        $taskCount = CRMNegotiationTask::query()
            ->where('crm_negotiation_id', $negotiation->id)
            ->count();

        expect($taskCount)->toBeGreaterThanOrEqual(1)
            ->and($taskCount)->toBeLessThanOrEqual(5);
    }
});

test('CRMNegotiationTaskSeeder respects tenant isolation', function (): void {
    // Arrange: create negotiations in different tenants with active users
    $otherTenant = PlatformTenant::factory()->create();

    // Create active users for both tenants
    AuthUser::factory()->create([
        'tenant_id' => $otherTenant->id,
        'is_active' => true,
    ]);

    CRMNegotiation::factory()
        ->count(2)
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    CRMNegotiation::factory()
        ->count(2)
        ->state(['tenant_id' => $otherTenant->id])
        ->create();

    // Act: run seeder
    $this->seed(CRMNegotiationTaskSeeder::class);

    // Assert: each tenant's tasks should belong only to their own negotiations
    $tenant1Negotiations = CRMNegotiation::query()
        ->where('tenant_id', $this->tenant->id)
        ->pluck('id');

    $tenant2Negotiations = CRMNegotiation::query()
        ->where('tenant_id', $otherTenant->id)
        ->pluck('id');

    // All tasks for tenant 1 should reference tenant 1 negotiations only
    $tenant1Tasks = CRMNegotiationTask::query()
        ->where('tenant_id', $this->tenant->id)
        ->get();

    $tenant2Tasks = CRMNegotiationTask::query()
        ->where('tenant_id', $otherTenant->id)
        ->get();

    foreach ($tenant1Tasks as $task) {
        expect($tenant1Negotiations)->toContain($task->crm_negotiation_id);
    }

    foreach ($tenant2Tasks as $task) {
        expect($tenant2Negotiations)->toContain($task->crm_negotiation_id);
    }
});

test('CRMNegotiationTaskSeeder assigns tasks to active users', function (): void {
    // Arrange: create active and inactive users
    $activeUsers = AuthUser::query()
        ->where('tenant_id', $this->tenant->id)
        ->where('is_active', true)
        ->pluck('id');

    AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => false,
    ]);

    $negotiation = CRMNegotiation::factory()
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    // Act: run seeder
    $this->seed(CRMNegotiationTaskSeeder::class);

    // Assert: tasks are only assigned to active users or null
    $tasks = CRMNegotiationTask::query()
        ->where('crm_negotiation_id', $negotiation->id)
        ->whereNotNull('auth_user_id')
        ->get();

    foreach ($tasks as $task) {
        expect($activeUsers)->toContain($task->auth_user_id);
    }
});

test('CRMNegotiationTaskSeeder creates tasks with valid status', function (): void {
    // Arrange
    CRMNegotiation::factory()
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    // Act: run seeder
    $this->seed(CRMNegotiationTaskSeeder::class);

    // Assert: all tasks have valid status
    $tasks = CRMNegotiationTask::query()->get();

    $validStatuses = ['pending', 'in_progress', 'done'];

    foreach ($tasks as $task) {
        expect($validStatuses)->toContain($task->status->value);
    }
});

test('CRMNegotiationTaskSeeder handles tenant without negotiations gracefully', function (): void {
    // Arrange: tenant without negotiations
    $emptyTenant = PlatformTenant::factory()->create();

    // Act: run seeder (should not throw exception)
    $this->seed(CRMNegotiationTaskSeeder::class);

    // Assert: no tasks created for empty tenant
    $tasks = CRMNegotiationTask::query()
        ->where('tenant_id', $emptyTenant->id)
        ->count();

    expect($tasks)->toBe(0);
});

test('CRMNegotiationTaskSeeder replaces existing tasks when run again', function (): void {
    // Arrange: create negotiation with tasks
    $negotiation = CRMNegotiation::factory()
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    CRMNegotiationTask::factory()
        ->count(2)
        ->state([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_id' => $negotiation->id,
        ])
        ->create();

    $originalCount = \Domain\CRM\Models\CRMNegotiationTask::query()->count();

    // Act: run seeder again
    $this->seed(CRMNegotiationTaskSeeder::class);

    // Assert: old tasks were replaced with new ones
    $newCount = CRMNegotiationTask::query()
        ->where('crm_negotiation_id', $negotiation->id)
        ->count();

    expect($newCount)->toBeGreaterThanOrEqual(1)
        ->and($newCount)->toBeLessThanOrEqual(5);
});

test('CRMNegotiationTaskSeeder creates tasks with all required fields', function (): void {
    // Arrange
    CRMNegotiation::factory()
        ->state(['tenant_id' => $this->tenant->id])
        ->create();

    // Act: run seeder
    $this->seed(CRMNegotiationTaskSeeder::class);

    // Assert: all tasks have required fields
    $tasks = CRMNegotiationTask::query()->get();

    foreach ($tasks as $task) {
        expect($task->id)->not->toBeEmpty()
            ->and($task->tenant_id)->not->toBeNull()
            ->and($task->crm_negotiation_id)->not->toBeNull()
            ->and($task->title)->not->toBeEmpty()
            ->and($task->status)->not->toBeEmpty()
            ->and($task->due_date)->not->toBeNull();
    }
});
