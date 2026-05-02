<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatRoutingQueue;
use Domain\Chat\Models\ChatRoutingQueueAgent;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatRoutingService;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->tenantId = (string) $this->tenant->id;
    TenantContext::set($this->tenantId);
});

afterEach(function (): void {
    TenantContext::clear();
});

function createRoutingQueue(string $tenantId, ?string $instanceId = null, bool $isEnabled = true, string $strategy = 'round_robin', ?int $maxOpen = null): ChatRoutingQueue
{
    return ChatRoutingQueue::create([
        'tenant_id' => $tenantId,
        'instance_id' => $instanceId,
        'name' => 'Test Queue',
        'is_enabled' => $isEnabled,
        'strategy' => $strategy,
        'max_open_tickets_per_agent' => $maxOpen,
    ]);
}

function createRoutingAgent(string $queueId, string $userId, int $position = 0, bool $isActive = true): ChatRoutingQueueAgent
{
    return ChatRoutingQueueAgent::create([
        'queue_id' => $queueId,
        'user_id' => $userId,
        'position' => $position,
        'is_active' => $isActive,
    ]);
}

function createRoutingTicket(string $tenantId, ?string $instanceId = null): ChatTicket
{
    return ChatTicket::factory()->forTenant($tenantId)->create([
        'instance_id' => $instanceId,
    ]);
}

it('distributes tickets sequentially in round robin among agents', function (): void {
    $queue = createRoutingQueue($this->tenantId);
    $agents = collect(range(1, 5))->map(fn (): AuthUser => AuthUser::factory()->create([
        'tenant_id' => $this->tenantId,
    ]))->all();

    foreach ($agents as $idx => $agent) {
        createRoutingAgent($queue->id, $agent->id, $idx);
    }

    $service = app(ChatRoutingService::class);
    $assignments = [];

    foreach (range(1, 10) as $i) {
        $ticket = createRoutingTicket($this->tenantId);
        $assigned = $service->route($ticket);
        $assignments[] = $assigned;
        usleep(1_100_000); // ensure distinct last_assigned_at timestamps (1.1s)
    }

    foreach ($agents as $agent) {
        $count = count(array_filter($assignments, fn (?string $id): bool => $id === $agent->id));

        expect($count)->toBe(2, "Agent {$agent->id} should have 2 tickets, got {$count}");
    }
})->group('integration');

it('uses FOR UPDATE SKIP LOCKED to prevent duplicate assignment', function (): void {
    $queue = createRoutingQueue($this->tenantId);
    $agent1 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    $agent2 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($queue->id, $agent1->id, 0);
    createRoutingAgent($queue->id, $agent2->id, 1);

    $capturedQueries = [];
    DB::listen(function ($query) use (&$capturedQueries): void {
        $capturedQueries[] = $query->sql;
    });

    $service = app(ChatRoutingService::class);
    $ticket = createRoutingTicket($this->tenantId);
    $service->route($ticket);

    $lockQuery = collect($capturedQueries)->first(fn (string $q): bool => str_contains($q, 'FOR UPDATE SKIP LOCKED'));

    expect($lockQuery)->not->toBeNull('Query should contain FOR UPDATE SKIP LOCKED')
        ->and($lockQuery)->toContain('FOR UPDATE SKIP LOCKED');
})->group('integration');

it('ignores inactive agents', function (): void {
    $queue = createRoutingQueue($this->tenantId);
    $activeAgent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    $inactiveAgent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($queue->id, $activeAgent->id, 0, true);
    createRoutingAgent($queue->id, $inactiveAgent->id, 1, false);

    $service = app(ChatRoutingService::class);
    $ticket = createRoutingTicket($this->tenantId);

    $assigned = $service->route($ticket);

    expect($assigned)->toBe($activeAgent->id);
});

it('returns null when queue is disabled', function (): void {
    $queue = createRoutingQueue($this->tenantId, null, false);
    $agent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($queue->id, $agent->id);

    $service = app(ChatRoutingService::class);
    $ticket = createRoutingTicket($this->tenantId);

    expect($service->route($ticket))->toBeNull();
});

it('uses channel queue when instance_id has active queue', function (): void {
    $instance = ChatInstance::factory()->create(['tenant_id' => $this->tenantId]);
    $channelQueue = createRoutingQueue($this->tenantId, $instance->id, true);
    $globalQueue = createRoutingQueue($this->tenantId, null, true);

    $channelAgent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    $globalAgent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($channelQueue->id, $channelAgent->id);
    createRoutingAgent($globalQueue->id, $globalAgent->id);

    $service = app(ChatRoutingService::class);
    $ticket = createRoutingTicket($this->tenantId, $instance->id);

    expect($service->route($ticket))->toBe($channelAgent->id);
});

it('falls back to global queue when channel has no queue', function (): void {
    $instance = ChatInstance::factory()->create(['tenant_id' => $this->tenantId]);
    $globalQueue = createRoutingQueue($this->tenantId, null, true);
    $globalAgent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($globalQueue->id, $globalAgent->id);

    $service = app(ChatRoutingService::class);
    $ticket = createRoutingTicket($this->tenantId, $instance->id);

    expect($service->route($ticket))->toBe($globalAgent->id);
});

it('returns null when no global queue and no channel override', function (): void {
    $instance = ChatInstance::factory()->create(['tenant_id' => $this->tenantId]);
    $service = app(ChatRoutingService::class);
    $ticket = createRoutingTicket($this->tenantId, $instance->id);

    expect($service->route($ticket))->toBeNull();
});

it('treats max_open_tickets_per_agent as unlimited when null', function (): void {
    $queue = createRoutingQueue($this->tenantId, null, true, 'round_robin', null);
    $agent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($queue->id, $agent->id);

    $service = app(ChatRoutingService::class);
    $ticket = createRoutingTicket($this->tenantId);

    expect($service->route($ticket))->toBe($agent->id);
})->group('integration');

// ── Least Busy ────────────────────────────────────────────────────────────

it('selects agent with fewest open tickets in least busy', function (): void {
    $queue = createRoutingQueue($this->tenantId, null, true, 'least_busy');
    $agent1 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    $agent2 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($queue->id, $agent1->id, 0);
    createRoutingAgent($queue->id, $agent2->id, 1);

    // Assign 3 open tickets to agent1, 1 to agent2
    foreach (range(1, 3) as $i) {
        ChatTicket::factory()->forTenant($this->tenantId)->create([
            'assigned_to' => $agent1->id,
            'status' => 'open',
        ]);
    }
    ChatTicket::factory()->forTenant($this->tenantId)->create([
        'assigned_to' => $agent2->id,
        'status' => 'open',
    ]);

    $service = app(ChatRoutingService::class);
    $ticket = createRoutingTicket($this->tenantId);

    expect($service->route($ticket))->toBe($agent2->id);
})->group('integration');

it('breaks least busy ties by last_assigned_at', function (): void {
    $queue = createRoutingQueue($this->tenantId, null, true, 'least_busy');
    $agent1 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    $agent2 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

    // Both have 0 open tickets; agent1 was assigned more recently
    $agent1Model = createRoutingAgent($queue->id, $agent1->id, 0);
    $agent1Model->last_assigned_at = now();
    $agent1Model->save();

    $agent2Model = createRoutingAgent($queue->id, $agent2->id, 1);
    $agent2Model->last_assigned_at = null;
    $agent2Model->save();

    $service = app(ChatRoutingService::class);
    $ticket = createRoutingTicket($this->tenantId);

    // agent2 has NULL last_assigned_at (sorts first) and same ticket count
    expect($service->route($ticket))->toBe($agent2->id);
})->group('integration');

it('respects max_open_tickets_per_agent in least busy', function (): void {
    $queue = createRoutingQueue($this->tenantId, null, true, 'least_busy', 2);
    $agent1 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    $agent2 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($queue->id, $agent1->id, 0);
    createRoutingAgent($queue->id, $agent2->id, 1);

    // agent1 already at limit (2 open tickets)
    foreach (range(1, 2) as $i) {
        ChatTicket::factory()->forTenant($this->tenantId)->create([
            'assigned_to' => $agent1->id,
            'status' => 'open',
        ]);
    }

    // agent2 at limit as well (2 open tickets)
    foreach (range(1, 2) as $i) {
        ChatTicket::factory()->forTenant($this->tenantId)->create([
            'assigned_to' => $agent2->id,
            'status' => 'open',
        ]);
    }

    $service = app(ChatRoutingService::class);
    $ticket = createRoutingTicket($this->tenantId);

    // Both at limit → no agent available
    expect($service->route($ticket))->toBeNull();
})->group('integration');

it('least_busy unlimited when max_open_tickets_per_agent is null', function (): void {
    $queue = createRoutingQueue($this->tenantId, null, true, 'least_busy', null);
    $agent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($queue->id, $agent->id);

    // Agent already has 5 open tickets
    foreach (range(1, 5) as $i) {
        ChatTicket::factory()->forTenant($this->tenantId)->create([
            'assigned_to' => $agent->id,
            'status' => 'open',
        ]);
    }

    $service = app(ChatRoutingService::class);
    $ticket = createRoutingTicket($this->tenantId);

    // Unlimited → still gets assigned despite 5 open tickets
    expect($service->route($ticket))->toBe($agent->id);
})->group('integration');

// ── Skill Based ───────────────────────────────────────────────────────────

it('selects agent with matching skill in skill_based', function (): void {
    $queue = createRoutingQueue($this->tenantId, null, true, 'skill_based');
    $agent1 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    $agent2 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($queue->id, $agent1->id, 0);
    createRoutingAgent($queue->id, $agent2->id, 1);

    // agent1 has skill 'suporte', agent2 has skill 'vendas'
    \Domain\Chat\Models\ChatRoutingAgentSkill::create([
        'queue_id' => $queue->id,
        'user_id' => $agent1->id,
        'skill' => 'suporte',
    ]);
    \Domain\Chat\Models\ChatRoutingAgentSkill::create([
        'queue_id' => $queue->id,
        'user_id' => $agent2->id,
        'skill' => 'vendas',
    ]);

    $service = app(ChatRoutingService::class);
    $ticket = ChatTicket::factory()->forTenant($this->tenantId)->create([
        'category' => 'suporte',
    ]);

    expect($service->route($ticket))->toBe($agent1->id);
})->group('integration');

it('returns null when no agent has matching skill', function (): void {
    $queue = createRoutingQueue($this->tenantId, null, true, 'skill_based');
    $agent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($queue->id, $agent->id, 0);

    // agent has skill 'vendas', ticket category is 'suporte'
    \Domain\Chat\Models\ChatRoutingAgentSkill::create([
        'queue_id' => $queue->id,
        'user_id' => $agent->id,
        'skill' => 'vendas',
    ]);

    $service = app(ChatRoutingService::class);
    $ticket = ChatTicket::factory()->forTenant($this->tenantId)->create([
        'category' => 'suporte',
    ]);

    expect($service->route($ticket))->toBeNull();
})->group('integration');

it('returns null when ticket has no category in skill_based', function (): void {
    $queue = createRoutingQueue($this->tenantId, null, true, 'skill_based');
    $agent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($queue->id, $agent->id, 0);

    \Domain\Chat\Models\ChatRoutingAgentSkill::create([
        'queue_id' => $queue->id,
        'user_id' => $agent->id,
        'skill' => 'suporte',
    ]);

    $service = app(ChatRoutingService::class);
    $ticket = ChatTicket::factory()->forTenant($this->tenantId)->create([
        'category' => null,
    ]);

    expect($service->route($ticket))->toBeNull();
})->group('integration');

it('applies round robin among agents with same matching skill', function (): void {
    $queue = createRoutingQueue($this->tenantId, null, true, 'skill_based');
    $agent1 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    $agent2 = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    createRoutingAgent($queue->id, $agent1->id, 0);
    createRoutingAgent($queue->id, $agent2->id, 1);

    // Both agents have skill 'suporte'
    \Domain\Chat\Models\ChatRoutingAgentSkill::create([
        'queue_id' => $queue->id,
        'user_id' => $agent1->id,
        'skill' => 'suporte',
    ]);
    \Domain\Chat\Models\ChatRoutingAgentSkill::create([
        'queue_id' => $queue->id,
        'user_id' => $agent2->id,
        'skill' => 'suporte',
    ]);

    $service = app(ChatRoutingService::class);

    // First ticket → agent1 (NULL last_assigned_at sorts first)
    $ticket1 = ChatTicket::factory()->forTenant($this->tenantId)->create([
        'category' => 'suporte',
    ]);
    expect($service->route($ticket1))->toBe($agent1->id);

    // Second ticket → agent2 (agent1 now has last_assigned_at)
    $ticket2 = ChatTicket::factory()->forTenant($this->tenantId)->create([
        'category' => 'suporte',
    ]);
    expect($service->route($ticket2))->toBe($agent2->id);
})->group('integration');
