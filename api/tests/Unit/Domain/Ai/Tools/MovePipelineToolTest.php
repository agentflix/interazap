<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Tools\MovePipelineTool;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class MovePipelineToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private MovePipelineTool $tool;

    private PlatformTenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new MovePipelineTool;
        $this->tenant = PlatformTenant::factory()->create();
    }

    public function test_it_implements_ai_tool_interface(): void
    {
        expect($this->tool)->toBeInstanceOf(AiToolInterface::class);
    }

    public function test_it_has_correct_name(): void
    {
        expect($this->tool->getName())->toBe('move_pipeline');
    }

    public function test_it_has_description(): void
    {
        expect($this->tool->getDescription())
            ->toBeString()
            ->not->toBeEmpty();
    }

    public function test_it_has_required_parameters(): void
    {
        $params = $this->tool->getParameters();

        expect($params)->toHaveKeys(['negotiation_id', 'step_id', 'reason']);
        expect($params['negotiation_id']['required'])->toBeTrue();
        expect($params['step_id']['required'])->toBeTrue();
        expect($params['reason']['required'])->toBeFalse();
    }

    public function test_it_moves_negotiation_to_new_step(): void
    {
        // Create funnel with the same tenant
        $funnel = CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        $newStep = CRMNegotiationFunnelStep::factory()->create([
            'crm_negotiation_funnel_id' => $funnel->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $oldStepId = $negotiation->crm_negotiation_funnel_step_id;

        $input = new ToolInputDTO(
            toolName: 'move_pipeline',
            parameters: [
                'negotiation_id' => $negotiation->id,
                'step_id' => $newStep->id,
                'reason' => 'Customer showed high interest',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result)->toBeInstanceOf(ToolResultDTO::class);
        expect($result->success)->toBeTrue();
        expect($result->data['previous_step_id'])->toBe($oldStepId);
        expect($result->data['new_step_id'])->toBe($newStep->id);

        $negotiation->refresh();
        expect($negotiation->crm_negotiation_funnel_step_id)->toBe($newStep->id);
    }

    public function test_it_fails_when_negotiation_not_found(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $step = CRMNegotiationFunnelStep::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        $input = new ToolInputDTO(
            toolName: 'move_pipeline',
            parameters: [
                'negotiation_id' => '00000000-0000-0000-0000-000000099999',
                'step_id' => $step->id,
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('Negotiation not found');
    }

    public function test_it_fails_when_step_not_found(): void
    {
        $funnel = CRMNegotiationFunnel::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'crm_negotiation_funnel_id' => $funnel->id,
        ]);

        $input = new ToolInputDTO(
            toolName: 'move_pipeline',
            parameters: [
                'negotiation_id' => $negotiation->id,
                'step_id' => '00000000-0000-0000-0000-000000099999',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('Step not found');
    }
}
