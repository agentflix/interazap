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

test('public view works with valid token', function (): void {
    $proposal = CRMProposal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'status' => 'sent',
        'public_token' => 'public-token-1',
    ]);

    $response = $this->getJson('/api/crm/proposals/view/public-token-1');

    $response->assertOk()->assertJsonFragment(['id' => $proposal->id]);
});

test('public accept marks proposal accepted', function (): void {
    $proposal = CRMProposal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'status' => 'sent',
        'public_token' => 'public-token-2',
    ]);

    $response = $this->postJson('/api/crm/proposals/public-token-2/accept');

    $response->assertOk();

    $proposal->refresh();
    expect($proposal->status->value)->toBe('accepted');
    expect($proposal->accepted_at)->not->toBeNull();
});

test('public reject marks proposal rejected', function (): void {
    $proposal = CRMProposal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_id' => $this->negotiation->id,
        'status' => 'sent',
        'public_token' => 'public-token-3',
    ]);

    $response = $this->postJson('/api/crm/proposals/public-token-3/reject');

    $response->assertOk();

    $proposal->refresh();
    expect($proposal->status->value)->toBe('rejected');
    expect($proposal->rejected_at)->not->toBeNull();
});
