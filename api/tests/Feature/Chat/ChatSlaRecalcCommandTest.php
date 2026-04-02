<?php

declare(strict_types=1);

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->instance = ChatInstance::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('sla recalc command marks first response breached tickets', function (): void {
    // Create ticket with expired first response SLA
    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'instance_id' => $this->instance->id,
        'status' => 'open',
        'sla_first_response_due_at' => now()->subHour(),
        'sla_first_response_breached' => false,
        'sla_resolution_breached' => false,
    ]);

    $this->artisan('chat:sla-recalc')
        ->assertSuccessful();

    $ticket->refresh();
    $ticket->load('extended');
    expect($ticket->sla_first_response_breached)->toBeTrue();
});

test('sla recalc command marks resolution breached tickets', function (): void {
    // Create ticket with expired resolution SLA
    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'instance_id' => $this->instance->id,
        'status' => 'open',
        'sla_resolution_due_at' => now()->subHour(),
        'sla_first_response_breached' => false,
        'sla_resolution_breached' => false,
    ]);

    $this->artisan('chat:sla-recalc')
        ->assertSuccessful();

    $ticket->refresh();
    $ticket->load('extended');
    expect($ticket->sla_resolution_breached)->toBeTrue();
});

test('sla recalc command does not affect closed tickets', function (): void {
    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'instance_id' => $this->instance->id,
        'status' => 'closed',
        'sla_first_response_due_at' => now()->subHour(),
        'sla_first_response_breached' => false,
    ]);

    $this->artisan('chat:sla-recalc')
        ->assertSuccessful();

    $ticket->refresh();
    $ticket->load('extended');
    expect($ticket->sla_first_response_breached)->toBeFalse();
});

test('sla recalc command does not affect non-expired SLAs', function (): void {
    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'instance_id' => $this->instance->id,
        'status' => 'open',
        'sla_first_response_due_at' => now()->addHour(),
        'sla_first_response_breached' => false,
    ]);

    $this->artisan('chat:sla-recalc')
        ->assertSuccessful();

    $ticket->refresh();
    $ticket->load('extended');
    expect($ticket->sla_first_response_breached)->toBeFalse();
});
