<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatRoutingQueue;
use Domain\Chat\Models\ChatRoutingQueueAgent;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

function createRoutingUser(?string $permission = null): AuthUser
{
    $user = AuthUser::factory()->create();

    if ($permission !== null) {
        $perm = AuthPermission::query()->firstOrCreate(
            ['name' => $permission, 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($perm);
    }

    return $user;
}

function createSuperAdminUser(): AuthUser
{
    $user = AuthUser::factory()->create();

    $role = \Domain\Auth\Models\AuthRole::query()->firstOrCreate(
        ['id' => \Domain\Auth\Models\AuthRole::ADMINISTRADOR_ID, 'name' => \Domain\Auth\Models\AuthRole::ADMINISTRADOR_NAME, 'guard_name' => 'sanctum'],
        ['id' => \Domain\Auth\Models\AuthRole::ADMINISTRADOR_ID]
    );
    $user->assignRole($role);

    return $user;
}

function createControllerQueue(string $tenantId, ?string $instanceId = null, bool $isEnabled = true): ChatRoutingQueue
{
    return \Domain\Chat\Models\ChatRoutingQueue::query()->create([
        'tenant_id' => $tenantId,
        'instance_id' => $instanceId,
        'name' => 'Test Queue',
        'is_enabled' => $isEnabled,
        'strategy' => 'round_robin',
    ]);
}

function createControllerAgent(string $queueId, string $userId, int $position = 0): ChatRoutingQueueAgent
{
    return \Domain\Chat\Models\ChatRoutingQueueAgent::query()->create([
        'queue_id' => $queueId,
        'user_id' => $userId,
        'position' => $position,
        'is_active' => true,
    ]);
}

// ── Global CRUD ───────────────────────────────────────────────────────────

it('shows global queue', function (): void {
    $user = createSuperAdminUser();
    $queue = createControllerQueue((string) $user->tenant_id);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/chat/routing-queue/global')
        ->assertOk()
        ->assertJsonPath('data.id', $queue->id);
});

it('creates global queue', function (): void {
    $user = createSuperAdminUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/chat/routing-queue/global', [
            'name' => 'Global Routing',
            'strategy' => 'round_robin',
            'is_enabled' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Global Routing')
        ->assertJsonPath('data.instance_id', null);
});

it('updates global queue', function (): void {
    $user = createSuperAdminUser();
    $queue = createControllerQueue((string) $user->tenant_id);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/chat/routing-queue/global', [
            'name' => 'Updated Global',
            'strategy' => 'round_robin',
            'is_enabled' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Global')
        ->assertJsonPath('data.is_enabled', false);
});

// ── Channel CRUD ──────────────────────────────────────────────────────────

it('shows channel queue', function (): void {
    $user = createSuperAdminUser();
    $instance = ChatInstance::factory()->create(['tenant_id' => $user->tenant_id]);
    $queue = createControllerQueue((string) $user->tenant_id, $instance->id);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/chat/channels/{$instance->id}/routing-queue")
        ->assertOk()
        ->assertJsonPath('data.id', $queue->id);
});

it('creates channel queue', function (): void {
    $user = createSuperAdminUser();
    $instance = ChatInstance::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/chat/channels/{$instance->id}/routing-queue", [
            'name' => 'Channel Routing',
            'strategy' => 'round_robin',
            'is_enabled' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Channel Routing')
        ->assertJsonPath('data.instance_id', $instance->id);
});

it('updates channel queue', function (): void {
    $user = createSuperAdminUser();
    $instance = ChatInstance::factory()->create(['tenant_id' => $user->tenant_id]);
    $queue = createControllerQueue((string) $user->tenant_id, $instance->id);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/chat/channels/{$instance->id}/routing-queue", [
            'name' => 'Updated Channel',
            'strategy' => 'round_robin',
            'is_enabled' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Channel')
        ->assertJsonPath('data.is_enabled', false);
});

// ── Agents ────────────────────────────────────────────────────────────────

it('adds agent to global queue', function (): void {
    $user = createSuperAdminUser();
    createControllerQueue((string) $user->tenant_id);
    $agent = AuthUser::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/chat/routing-queue/global/agents', [
            'user_id' => $agent->id,
            'position' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $agent->id);
});

it('returns 403 when adding agent from another tenant', function (): void {
    $user = createRoutingUser('chat.routing.manage');
    createControllerQueue((string) $user->tenant_id);
    $otherTenant = PlatformTenant::factory()->create();
    $otherAgent = AuthUser::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/chat/routing-queue/global/agents', [
            'user_id' => $otherAgent->id,
            'position' => 1,
        ])
        ->assertForbidden();
});

it('reorders agents', function (): void {
    $user = createSuperAdminUser();
    $queue = createControllerQueue((string) $user->tenant_id);
    $agent1 = AuthUser::factory()->create(['tenant_id' => $user->tenant_id]);
    $agent2 = AuthUser::factory()->create(['tenant_id' => $user->tenant_id]);
    createControllerAgent($queue->id, $agent1->id, 0);
    createControllerAgent($queue->id, $agent2->id, 1);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/chat/routing-queue/global/agents/reorder', [
            'agents' => [
                ['user_id' => $agent1->id, 'position' => 5],
                ['user_id' => $agent2->id, 'position' => 3],
            ],
        ])
        ->assertOk();

    $queueAgent1 = ChatRoutingQueueAgent::query()
        ->where('queue_id', $queue->id)
        ->where('user_id', $agent1->id)
        ->first();
    $queueAgent2 = ChatRoutingQueueAgent::query()
        ->where('queue_id', $queue->id)
        ->where('user_id', $agent2->id)
        ->first();

    expect($queueAgent1->position)->toBe(5)
        ->and($queueAgent2->position)->toBe(3);
});

// ── Permissions ───────────────────────────────────────────────────────────

it('returns 403 on global GET without chat.routing.view permission', function (): void {
    $user = AuthUser::factory()->create();
    createControllerQueue((string) $user->tenant_id);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/chat/routing-queue/global')
        ->assertForbidden();
});

it('returns 403 on channel GET without chat.routing.view permission', function (): void {
    $user = AuthUser::factory()->create();
    $instance = ChatInstance::factory()->create(['tenant_id' => $user->tenant_id]);
    createControllerQueue((string) $user->tenant_id, $instance->id);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/chat/channels/{$instance->id}/routing-queue")
        ->assertForbidden();
});

it('returns 403 on POST without chat.routing.manage permission', function (): void {
    $user = AuthUser::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/chat/routing-queue/global', [
            'name' => 'Test',
            'strategy' => 'round_robin',
        ])
        ->assertForbidden();
});

it('returns 403 on PUT without chat.routing.manage permission', function (): void {
    $user = AuthUser::factory()->create();
    $queue = createControllerQueue((string) $user->tenant_id);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/chat/routing-queue/global', [
            'name' => 'Updated',
        ])
        ->assertForbidden();
});

it('returns 403 on channel POST without chat.routing.manage permission', function (): void {
    $user = AuthUser::factory()->create();
    $instance = ChatInstance::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/chat/channels/{$instance->id}/routing-queue", [
            'name' => 'Test',
            'strategy' => 'round_robin',
        ])
        ->assertForbidden();
});
