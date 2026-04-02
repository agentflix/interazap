<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMProposal;
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

test('can create proposal', function (): void {
    $payload = [
        'title' => 'Proposta Comercial 2024',
        'items' => [
            [
                'name' => 'Produto A',
                'quantity' => 2,
                'unit_price' => 100.00,
            ],
        ],
    ];

    $response = $this->postJson("/api/crm/negotiations/{$this->negotiation->id}/proposals", $payload);

    $response->assertCreated()
        ->assertJsonFragment(['title' => 'Proposta Comercial 2024']);

    $this->assertDatabaseHas('crm_proposals', [
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'title' => 'Proposta Comercial 2024',
        'status' => 'draft',
    ]);
});

test('send generates public token', function (): void {
    $proposal = CRMProposal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'status' => 'draft',
    ]);

    $response = $this->postJson("/api/crm/proposals/{$proposal->id}/send");

    $response->assertOk();

    $proposal->refresh();
    expect($proposal->status->value)->toBe('sent');
    expect($proposal->public_token)->not->toBeNull();
    expect($proposal->sent_at)->not->toBeNull();
});

test('public view with valid token', function (): void {
    $proposal = CRMProposal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'status' => 'sent',
        'public_token' => 'valid-token-123',
    ]);

    $response = $this->getJson('/api/crm/proposals/view/valid-token-123');

    $response->assertOk()
        ->assertJsonFragment(['title' => $proposal->title]);
});

test('public view marks viewed_at', function (): void {
    $proposal = CRMProposal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'status' => 'sent',
        'public_token' => 'valid-token-456',
        'viewed_at' => null,
    ]);

    $this->getJson('/api/crm/proposals/view/valid-token-456');

    $proposal->refresh();
    expect($proposal->viewed_at)->not->toBeNull();
});

test('accept changes status to accepted', function (): void {
    $proposal = CRMProposal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'status' => 'sent',
        'public_token' => 'token-accept',
    ]);

    $response = $this->postJson('/api/crm/proposals/token-accept/accept');

    $response->assertOk();

    $proposal->refresh();
    expect($proposal->status->value)->toBe('accepted');
    expect($proposal->accepted_at)->not->toBeNull();
});

test('accept marks negotiation won', function (): void {
    $proposal = CRMProposal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'status' => 'sent',
        'public_token' => 'token-won',
    ]);

    $this->postJson('/api/crm/proposals/token-won/accept');

    $this->negotiation->refresh();
    expect($this->negotiation->status->value)->toBe('won');
});

test('reject keeps negotiation open and marks proposal rejected', function (): void {
    $proposal = CRMProposal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'status' => 'sent',
        'public_token' => 'token-reject',
    ]);

    $response = $this->postJson('/api/crm/proposals/token-reject/reject');

    $response->assertOk();

    $proposal->refresh();
    $this->negotiation->refresh();

    expect($proposal->status->value)->toBe('rejected');
    expect($proposal->rejected_at)->not->toBeNull();
    expect($this->negotiation->status->value)->toBe('open');
});

test('duplicate creates copy', function (): void {
    $proposal = CRMProposal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'title' => 'Original Proposal',
        'status' => 'sent',
    ]);

    $response = $this->postJson("/api/crm/proposals/{$proposal->id}/duplicate");

    $response->assertCreated();

    $this->assertDatabaseHas('crm_proposals', [
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'status' => 'draft',
    ]);

    $proposals = \Domain\CRM\Models\CRMProposal::query()->where('crm_negotiation_id', $this->negotiation->id)->get();
    expect($proposals)->toHaveCount(2);
});
