<?php

declare(strict_types=1);

use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\Tools\CreateNoteTool;
use Domain\Ai\Tools\CreateTaskTool;
use Domain\Ai\Tools\GetContactInfoTool;
use Domain\Ai\Tools\MovePipelineTool;
use Domain\Ai\Tools\UpdateContactTagsTool;
use Domain\Ai\Tools\UpdateLeadScoreTool;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMNegotiationTask;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->contact = CRMContact::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->funnel = CRMNegotiationFunnel::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->step = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
    ]);

    $this->negotiation = CRMNegotiation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
        'crm_negotiation_funnel_step_id' => $this->step->id,
    ]);
});

test('get_contact_info tool returns contact data', function (): void {
    $tag = CRMTag::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->contact->tags()->attach($tag->id, [
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $this->tenant->id,
    ]);

    $tool = new GetContactInfoTool;
    $input = new ToolInputDTO(
        toolName: 'get_contact_info',
        parameters: ['contact_id' => $this->contact->id],
        context: ['tenant_id' => $this->tenant->id],
    );

    $result = $tool->handle($input);

    expect($result->success)->toBeTrue();
    expect($result->data['contact']['id'])->toBe($this->contact->id);
    expect($result->data['contact']['tags'])->toContain($tag->name);
});

test('update_contact_tags tool adds tags', function (): void {
    $tool = new UpdateContactTagsTool;
    $input = new ToolInputDTO(
        toolName: 'update_contact_tags',
        parameters: [
            'contact_id' => $this->contact->id,
            'tags' => ['VIP'],
            'action' => 'add',
        ],
        context: ['tenant_id' => $this->tenant->id],
    );

    $result = $tool->handle($input);

    expect($result->success)->toBeTrue();
    expect($this->contact->refresh()->tags->pluck('name')->toArray())->toContain('VIP');
});

test('create_note tool creates a note for contact', function (): void {
    $tool = new CreateNoteTool;
    $input = new ToolInputDTO(
        toolName: 'create_note',
        parameters: [
            'entity_type' => 'contact',
            'entity_id' => $this->contact->id,
            'content' => 'Important note',
        ],
        context: ['tenant_id' => $this->tenant->id],
    );

    $result = $tool->handle($input);

    expect($result->success)->toBeTrue();
    $this->assertDatabaseHas('crm_notes', [
        'id' => $result->data['note_id'],
        'entity_type' => 'contact',
        'entity_id' => $this->contact->id,
        'content' => 'Important note',
    ]);
});

test('create_task tool creates a negotiation task', function (): void {
    $tool = new CreateTaskTool;
    $input = new ToolInputDTO(
        toolName: 'create_task',
        parameters: [
            'title' => 'Follow up',
            'description' => 'Call the customer',
            'due_date' => now()->addDay()->toIso8601String(),
            'negotiation_id' => $this->negotiation->id,
        ],
        context: ['tenant_id' => $this->tenant->id],
    );

    $result = $tool->handle($input);

    expect($result->success)->toBeTrue();
    expect(CRMNegotiationTask::query()->where('id', $result->data['task_id'])->exists())->toBeTrue();
});

test('move_pipeline tool updates negotiation step', function (): void {
    $nextStep = CRMNegotiationFunnelStep::factory()->create([
        'tenant_id' => $this->tenant->id,
        'crm_negotiation_funnel_id' => $this->funnel->id,
    ]);

    $tool = new MovePipelineTool;
    $input = new ToolInputDTO(
        toolName: 'move_pipeline',
        parameters: [
            'negotiation_id' => $this->negotiation->id,
            'step_id' => $nextStep->id,
        ],
        context: ['tenant_id' => $this->tenant->id],
    );

    $result = $tool->handle($input);

    expect($result->success)->toBeTrue();
    expect($this->negotiation->refresh()->crm_negotiation_funnel_step_id)->toBe($nextStep->id);
});

test('update_lead_score tool updates negotiation score', function (): void {
    $tool = new UpdateLeadScoreTool;
    $input = new ToolInputDTO(
        toolName: 'update_lead_score',
        parameters: [
            'negotiation_id' => $this->negotiation->id,
            'score' => 80,
            'reason' => 'High engagement',
        ],
        context: ['tenant_id' => $this->tenant->id],
    );

    $result = $tool->handle($input);

    expect($result->success)->toBeTrue();
    expect($this->negotiation->refresh()->lead_score)->toBe(80);
});

test('crm tools enforce tenant isolation', function (): void {
    $otherTenant = PlatformTenant::factory()->create();

    $tool = new GetContactInfoTool;
    $input = new ToolInputDTO(
        toolName: 'get_contact_info',
        parameters: ['contact_id' => $this->contact->id],
        context: ['tenant_id' => $otherTenant->id],
    );

    $result = $tool->handle($input);

    expect($result->success)->toBeFalse();
});
