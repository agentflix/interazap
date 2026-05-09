<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Actions\CreateChatTicketAction;
use Domain\Chat\DTOs\ChatTicketDTO;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->tenantId = (string) $this->tenant->id;
    TenantContext::set($this->tenantId);
});

afterEach(function (): void {
    TenantContext::clear();
});

it('assigns ticket to agent when active queue exists', function (): void {
    $queue = \Domain\Chat\Models\ChatRoutingQueue::query()->create([
        'tenant_id' => $this->tenantId,
        'instance_id' => null,
        'name' => 'Global Queue',
        'is_enabled' => true,
        'strategy' => 'round_robin',
    ]);
    $agent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    \Domain\Chat\Models\ChatRoutingQueueAgent::query()->create([
        'queue_id' => $queue->id,
        'user_id' => $agent->id,
        'position' => 0,
        'is_active' => true,
    ]);

    $action = app(CreateChatTicketAction::class);
    $dto = new ChatTicketDTO(channel: 'whatsapp');
    $ticket = $action->create($this->tenantId, $dto);

    expect($ticket->assigned_to)->toBe($agent->id)
        ->and($ticket->status)->toBe('open');
});

it('leaves assigned_to null when no queue is configured', function (): void {
    $action = app(CreateChatTicketAction::class);
    $dto = new ChatTicketDTO(channel: 'whatsapp');
    $ticket = $action->create($this->tenantId, $dto);

    expect($ticket->assigned_to)->toBeNull()
        ->and($ticket->status)->toBe('pending');
});

it('creates ticket with assigned_to null when routing service is unavailable', function (): void {
    $action = app(CreateChatTicketAction::class);
    $dto = new ChatTicketDTO(channel: 'whatsapp');
    $ticket = $action->create($this->tenantId, $dto);

    expect($ticket)->toBeInstanceOf(ChatTicket::class)
        ->and($ticket->assigned_to)->toBeNull()
        ->and($ticket->status)->toBe('pending')
        ->and(ChatTicket::query()->where('id', $ticket->id)->exists())->toBeTrue();
});

it('uses channel queue over global queue for instance ticket', function (): void {
    $instance = ChatInstance::factory()->create(['tenant_id' => $this->tenantId]);
    $channelQueue = \Domain\Chat\Models\ChatRoutingQueue::query()->create([
        'tenant_id' => $this->tenantId,
        'instance_id' => $instance->id,
        'name' => 'Channel Queue',
        'is_enabled' => true,
        'strategy' => 'round_robin',
    ]);
    $globalQueue = \Domain\Chat\Models\ChatRoutingQueue::query()->create([
        'tenant_id' => $this->tenantId,
        'instance_id' => null,
        'name' => 'Global Queue',
        'is_enabled' => true,
        'strategy' => 'round_robin',
    ]);

    $channelAgent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    $globalAgent = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);
    \Domain\Chat\Models\ChatRoutingQueueAgent::query()->create(['queue_id' => $channelQueue->id, 'user_id' => $channelAgent->id, 'position' => 0, 'is_active' => true]);
    \Domain\Chat\Models\ChatRoutingQueueAgent::query()->create(['queue_id' => $globalQueue->id, 'user_id' => $globalAgent->id, 'position' => 0, 'is_active' => true]);

    $action = app(CreateChatTicketAction::class);
    $dto = new ChatTicketDTO(channel: 'whatsapp', instanceId: $instance->id);
    $ticket = $action->create($this->tenantId, $dto);

    expect($ticket->assigned_to)->toBe($channelAgent->id);
});
