<?php

declare(strict_types=1);

use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\Tools\CreateProposalTool;
use Domain\Ai\Tools\LinkContactToCompanyTool;
use Domain\Ai\Tools\UpdateCompanyTool;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMProduct;
use Domain\CRM\Models\CRMProposal;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->otherTenant = PlatformTenant::factory()->create();

    $this->company = CRMCompany::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Empresa Original',
    ]);

    $this->contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_company_id' => null,
    ]);

    $this->funnel = CRMNegotiationFunnel::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->step = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
    ]);

    $this->negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_contact_id' => $this->contact->id,
        'crm_company_id' => $this->company->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
    ]);
});

test('update_company updates allowed fields', function (): void {
    $tool = new UpdateCompanyTool;

    $result = $tool->handle(new ToolInputDTO(
        toolName: 'update_company',
        parameters: [
            'company_id' => $this->company->id,
            'name' => 'Empresa Atualizada',
            'city' => 'São Paulo',
        ],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect($this->company->refresh()->name)->toBe('Empresa Atualizada');
});

test('create_proposal creates proposal with items', function (): void {
    $product = CRMProduct::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Plano Premium',
        'price' => 250,
    ]);

    $tool = new CreateProposalTool;
    $result = $tool->handle(new ToolInputDTO(
        toolName: 'create_proposal',
        parameters: [
            'negotiation_id' => $this->negotiation->id,
            'title' => 'Proposta Comercial',
            'valid_until' => now()->addDays(7)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 300,
                    'discount' => 50,
                ],
            ],
        ],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();

    $proposal = CRMProposal::query()->find($result->data['proposal_id']);
    expect($proposal)->not->toBeNull();
    expect($proposal?->items()->count())->toBe(1);
    expect((float) $proposal?->total)->toBe(550.0);
});

test('link_contact_to_company links entities and sets main company', function (): void {
    $tool = new LinkContactToCompanyTool;

    $result = $tool->handle(new ToolInputDTO(
        toolName: 'link_contact_to_company',
        parameters: [
            'contact_id' => $this->contact->id,
            'company_id' => $this->company->id,
        ],
        context: ['tenant_id' => $this->tenant->id],
    ));

    expect($result->success)->toBeTrue();
    expect($this->contact->refresh()->crm_company_id)->toBe($this->company->id);
    expect($this->contact->companies()->where('crm_companies.id', $this->company->id)->exists())->toBeTrue();
});

test('wave 3 tools enforce tenant isolation', function (): void {
    $tool = new UpdateCompanyTool;

    $result = $tool->handle(new ToolInputDTO(
        toolName: 'update_company',
        parameters: [
            'company_id' => $this->company->id,
            'name' => 'Inválido',
        ],
        context: ['tenant_id' => $this->otherTenant->id],
    ));

    expect($result->success)->toBeFalse();
});
