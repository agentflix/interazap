<?php

declare(strict_types=1);

use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\Tools\CheckAvailabilityTool;
use Domain\Ai\Tools\ListFunnelStepsTool;
use Domain\Ai\Tools\ListProductsTool;
use Domain\Ai\Tools\QualifyLeadTool;
use Domain\Ai\Tools\SearchContactsTool;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMEvent;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMProduct;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->otherTenant = PlatformTenant::factory()->create();

    $this->contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Maria Vendedora',
        'email' => 'maria@example.com',
        'phone' => '11999990000',
    ]);

    $this->funnel = CRMNegotiationFunnel::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Funil Principal',
    ]);

    $this->stepA = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'name' => 'Qualificação',
        'order' => 1,
    ]);

    $this->stepB = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'name' => 'Proposta',
        'order' => 2,
    ]);

    $this->negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $this->contact->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->stepA->id,
        'lead_score' => 10,
    ]);
});

test('qualify_lead updates score tags and step in one call', function (): void {
    $tool = new QualifyLeadTool;

    $result = $tool->handle(new ToolInputDTO(
        toolName: 'qualify_lead',
        parameters: [
            'negotiation_id' => $this->negotiation->id,
            'score' => 85,
            'tags' => ['hot', 'priority'],
            'step_id' => $this->stepB->id,
        ],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect($this->negotiation->refresh()->lead_score)->toBe(85);
    expect($this->negotiation->refresh()->crm_negotiation_funnel_step_id)->toBe($this->stepB->id);
    expect($this->contact->refresh()->tags->pluck('name')->toArray())->toContain('hot', 'priority');
});

test('search_contacts finds contacts by fuzzy query', function (): void {
    CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'João da Silva',
        'email' => 'joao@empresa.com',
        'phone' => '11888887777',
    ]);

    $tool = new SearchContactsTool;
    $result = $tool->handle(new ToolInputDTO(
        toolName: 'search_contacts',
        parameters: ['query' => 'joao', 'limit' => 5],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect($result->data['contacts'])->toHaveCount(1);
});

test('list_funnel_steps returns steps for tenant funnels', function (): void {
    $tool = new ListFunnelStepsTool;

    $result = $tool->handle(new ToolInputDTO(
        toolName: 'list_funnel_steps',
        parameters: ['funnel_id' => $this->funnel->id],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect($result->data['funnels'])->toHaveCount(1);
    expect($result->data['funnels'][0]['steps'])->toHaveCount(2);
});

test('list_products supports active filter', function (): void {
    CRMProduct::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
        'name' => 'Produto Ativo',
    ]);
    CRMProduct::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => false,
        'name' => 'Produto Inativo',
    ]);

    $tool = new ListProductsTool;
    $result = $tool->handle(new ToolInputDTO(
        toolName: 'list_products',
        parameters: ['active_only' => true],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect($result->data['products'])->toHaveCount(1);
    expect($result->data['products'][0]['name'])->toBe('Produto Ativo');
});

test('check_availability reports conflicts in range', function (): void {
    $startsAt = now()->addDay()->setHour(10)->setMinute(0);

    CRMEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Compromisso Existente',
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHour(),
    ]);

    $tool = new CheckAvailabilityTool;
    $result = $tool->handle(new ToolInputDTO(
        toolName: 'check_availability',
        parameters: [
            'date_from' => $startsAt->copy()->subMinutes(30)->toIso8601String(),
            'date_to' => $startsAt->copy()->addMinutes(30)->toIso8601String(),
        ],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect($result->data['is_available'])->toBeFalse();
    expect($result->data['conflicts'])->toHaveCount(1);
});

test('wave 2 tools enforce tenant isolation', function (): void {
    $tool = new SearchContactsTool;

    $result = $tool->handle(new ToolInputDTO(
        toolName: 'search_contacts',
        parameters: ['query' => 'Maria'],
        context: ['tenant_id' => $this->otherTenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect($result->data['contacts'])->toHaveCount(0);
});
