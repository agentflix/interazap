<?php

declare(strict_types=1);

use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\Tools\AddProductToNegotiationTool;
use Domain\Ai\Tools\CloseNegotiationTool;
use Domain\Ai\Tools\CreateCompanyTool;
use Domain\Ai\Tools\CreateContactTool;
use Domain\Ai\Tools\CreateNegotiationTool;
use Domain\Ai\Tools\GetNegotiationInfoTool;
use Domain\Ai\Tools\ScheduleEventTool;
use Domain\Ai\Tools\UpdateContactTool;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMEvent;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMNegotiationProduct;
use Domain\CRM\Models\CRMProduct;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->otherTenant = PlatformTenant::factory()->create();

    $this->contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->funnel = CRMNegotiationFunnel::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->step = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
    ]);
});

test('update_contact updates provided fields', function (): void {
    $tool = new UpdateContactTool;

    $result = $tool->handle(new ToolInputDTO(
        toolName: 'update_contact',
        parameters: [
            'contact_id' => $this->contact->id,
            'name' => 'Contato Atualizado',
            'city' => 'Campinas',
            'phone' => '+5511999990000',
        ],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect($this->contact->refresh()->name)->toBe('Contato Atualizado');
    expect(data_get($this->contact->custom_fields, 'city'))->toBe('Campinas');
});

test('create_contact requires mandatory fields', function (): void {
    $tool = new CreateContactTool;

    $result = $tool->handle(new ToolInputDTO(
        toolName: 'create_contact',
        parameters: ['name' => ''],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeFalse();
});

test('create_company creates a tenant scoped company', function (): void {
    $tool = new CreateCompanyTool;

    $result = $tool->handle(new ToolInputDTO(
        toolName: 'create_company',
        parameters: [
            'name' => 'ACME LTDA',
            'document' => '12.345.678/0001-90',
        ],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect(CRMCompany::query()->where('id', $result->data['company_id'])->exists())->toBeTrue();
});

test('create_negotiation validates contact and step by tenant', function (): void {
    $tool = new CreateNegotiationTool;

    $result = $tool->handle(new ToolInputDTO(
        toolName: 'create_negotiation',
        parameters: [
            'title' => 'Novo Deal',
            'contact_id' => $this->contact->id,
            'step_id' => $this->step->id,
            'amount' => 350.5,
        ],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect(CRMNegotiation::query()->where('id', $result->data['negotiation_id'])->exists())->toBeTrue();
});

test('close_negotiation closes only open negotiations', function (): void {
    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $this->contact->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'status' => 'open',
    ]);

    $tool = new CloseNegotiationTool;
    $result = $tool->handle(new ToolInputDTO(
        toolName: 'close_negotiation',
        parameters: [
            'negotiation_id' => $negotiation->id,
            'outcome' => 'won',
        ],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect($negotiation->refresh()->status->value)->toBe('won');
});

test('schedule_event creates event and participant', function (): void {
    $tool = new ScheduleEventTool;

    $result = $tool->handle(new ToolInputDTO(
        toolName: 'schedule_event',
        parameters: [
            'title' => 'Reunião Comercial',
            'starts_at' => now()->addDay()->toIso8601String(),
            'ends_at' => now()->addDay()->addHour()->toIso8601String(),
            'contact_id' => $this->contact->id,
        ],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect(CRMEvent::query()->where('id', $result->data['event_id'])->exists())->toBeTrue();
});

test('get_negotiation_info returns related data', function (): void {
    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $this->contact->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
    ]);

    $tool = new GetNegotiationInfoTool;
    $result = $tool->handle(new ToolInputDTO(
        toolName: 'get_negotiation_info',
        parameters: ['negotiation_id' => $negotiation->id],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect($result->data['negotiation']['id'])->toBe($negotiation->id);
});

test('add_product_to_negotiation adds item and updates amount', function (): void {
    $product = CRMProduct::factory()->create([
        'tenant_id' => $this->tenant->id,
        'price' => 100,
    ]);

    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $this->contact->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
        'amount' => 0,
    ]);

    $tool = new AddProductToNegotiationTool;
    $result = $tool->handle(new ToolInputDTO(
        toolName: 'add_product_to_negotiation',
        parameters: [
            'negotiation_id' => $negotiation->id,
            'product_id' => $product->id,
            'qty' => 2,
            'unit_price' => 150,
        ],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect(CRMNegotiationProduct::query()->where('id', $result->data['negotiation_product_id'])->exists())->toBeTrue();
    expect((float) $negotiation->refresh()->amount)->toBe(300.0);
});

test('wave 1 tools enforce tenant isolation', function (): void {
    $tool = new GetNegotiationInfoTool;

    $negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $this->contact->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
    ]);

    $result = $tool->handle(new ToolInputDTO(
        toolName: 'get_negotiation_info',
        parameters: ['negotiation_id' => $negotiation->id],
        context: ['tenant_id' => $this->otherTenant->id],
    ));

    expect($result->success)->toBeFalse();
});
