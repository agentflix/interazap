<?php

declare(strict_types=1);

use Domain\Ai\Jobs\AiRunExecutionJob;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentDelegation;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Services\AiAgentDelegationService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

it('blocks delegation when max depth is reached', function (): void {
    Queue::fake();

    $tenant = PlatformTenant::factory()->create();
    $tenantId = (string) $tenant->id;

    $sourceAgent = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Source Agent',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
        'is_active' => true,
    ]);

    $targetAgent = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Target Agent',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
        'is_active' => true,
    ]);

    AiAgentDelegation::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'source_agent_id' => (string) $sourceAgent->id,
        'target_agent_id' => (string) $targetAgent->id,
        'max_depth' => 1,
        'is_active' => true,
    ]);

    $parentRun = AiAutopilotRun::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'playbook_version' => 1,
        'status' => 'running',
        'delegation_depth' => 1,
        'input_context' => [
            'agent_id' => (string) $sourceAgent->id,
        ],
    ]);

    $result = app(AiAgentDelegationService::class)->delegate(
        tenantId: $tenantId,
        parentRunId: (string) $parentRun->id,
        sourceAgentId: (string) $sourceAgent->id,
        targetAgentId: (string) $targetAgent->id,
    );

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Delegation max depth reached.');

    Queue::assertNotPushed(AiRunExecutionJob::class);
});

it('blocks circular delegation when target agent exists in parent chain', function (): void {
    Queue::fake();

    $tenant = PlatformTenant::factory()->create();
    $tenantId = (string) $tenant->id;

    $agentA = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Agent A',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
        'is_active' => true,
    ]);

    $agentB = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Agent B',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
        'is_active' => true,
    ]);

    AiAgentDelegation::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'source_agent_id' => (string) $agentB->id,
        'target_agent_id' => (string) $agentA->id,
        'max_depth' => 3,
        'is_active' => true,
    ]);

    $rootRun = AiAutopilotRun::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'playbook_version' => 1,
        'status' => 'running',
        'delegation_depth' => 0,
        'input_context' => [
            'agent_id' => (string) $agentA->id,
        ],
    ]);

    $childRun = AiAutopilotRun::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'playbook_version' => 1,
        'status' => 'running',
        'parent_run_id' => (string) $rootRun->id,
        'delegation_depth' => 1,
        'input_context' => [
            'agent_id' => (string) $agentB->id,
        ],
    ]);

    $result = app(AiAgentDelegationService::class)->delegate(
        tenantId: $tenantId,
        parentRunId: (string) $childRun->id,
        sourceAgentId: (string) $agentB->id,
        targetAgentId: (string) $agentA->id,
    );

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Circular delegation detected.');

    Queue::assertNotPushed(AiRunExecutionJob::class);
});

it('supports delegation chain up to max depth', function (): void {
    Queue::fake();

    $tenant = PlatformTenant::factory()->create();
    $tenantId = (string) $tenant->id;

    $agentA = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Agent A',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
        'is_active' => true,
    ]);

    $agentB = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Agent B',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
        'is_active' => true,
    ]);

    $agentC = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Agent C',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
        'is_active' => true,
    ]);

    AiAgentDelegation::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'source_agent_id' => (string) $agentA->id,
        'target_agent_id' => (string) $agentB->id,
        'max_depth' => 3,
        'is_active' => true,
    ]);

    AiAgentDelegation::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'source_agent_id' => (string) $agentB->id,
        'target_agent_id' => (string) $agentC->id,
        'max_depth' => 3,
        'is_active' => true,
    ]);

    $parentRun = AiAutopilotRun::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'playbook_version' => 1,
        'status' => 'running',
        'delegation_depth' => 0,
        'input_context' => [
            'agent_id' => (string) $agentA->id,
        ],
    ]);

    $firstDelegation = app(AiAgentDelegationService::class)->delegate(
        tenantId: $tenantId,
        parentRunId: (string) $parentRun->id,
        sourceAgentId: (string) $agentA->id,
        targetAgentId: (string) $agentB->id,
    );

    expect($firstDelegation['success'])->toBeTrue();

    $childRunId = (string) ($firstDelegation['child_run_id'] ?? '');
    $childRun = AiAutopilotRun::query()->findOrFail($childRunId);

    $secondDelegation = app(AiAgentDelegationService::class)->delegate(
        tenantId: $tenantId,
        parentRunId: (string) $childRun->id,
        sourceAgentId: (string) $agentB->id,
        targetAgentId: (string) $agentC->id,
    );

    expect($secondDelegation['success'])->toBeTrue();

    $secondChild = AiAutopilotRun::query()->findOrFail((string) ($secondDelegation['child_run_id'] ?? ''));

    expect((int) $childRun->delegation_depth)->toBe(1)
        ->and((int) $secondChild->delegation_depth)->toBe(2)
        ->and((string) $secondChild->parent_run_id)->toBe((string) $childRun->id);

    Queue::assertPushed(AiRunExecutionJob::class, 2);
});

it('creates child run without dispatching execution job when create-only mode is enabled', function (): void {
    Queue::fake();

    $tenant = PlatformTenant::factory()->create();
    $tenantId = (string) $tenant->id;

    $sourceAgent = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Source Agent',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
        'is_active' => true,
    ]);

    $targetAgent = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Target Agent',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
        'is_active' => true,
    ]);

    AiAgentDelegation::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'source_agent_id' => (string) $sourceAgent->id,
        'target_agent_id' => (string) $targetAgent->id,
        'max_depth' => 3,
        'is_active' => true,
    ]);

    $parentRun = AiAutopilotRun::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'playbook_version' => 1,
        'status' => 'running',
        'delegation_depth' => 0,
        'input_context' => [
            'agent_id' => (string) $sourceAgent->id,
        ],
    ]);

    $result = app(AiAgentDelegationService::class)->delegate(
        tenantId: $tenantId,
        parentRunId: (string) $parentRun->id,
        sourceAgentId: (string) $sourceAgent->id,
        targetAgentId: (string) $targetAgent->id,
        payload: ['input_context' => ['ticket_id' => 'ticket-1']],
        dispatchExecutionJob: false,
    );

    expect($result['success'])->toBeTrue();
    expect((string) ($result['child_run_id'] ?? ''))->not->toBe('');

    Queue::assertNotPushed(AiRunExecutionJob::class);
});
