<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Tools\UpdateLeadScoreTool;
use Domain\Crm\Models\CRMNegotiation;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class UpdateLeadScoreToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UpdateLeadScoreTool $tool;

    private PlatformTenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new UpdateLeadScoreTool;
        $this->tenant = PlatformTenant::factory()->create();
    }

    public function test_it_implements_ai_tool_interface(): void
    {
        expect($this->tool)->toBeInstanceOf(AiToolInterface::class);
    }

    public function test_it_has_correct_name(): void
    {
        expect($this->tool->getName())->toBe('update_lead_score');
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

        expect($params)->toHaveKeys(['negotiation_id', 'score', 'reason']);
        expect($params['negotiation_id']['required'])->toBeTrue();
        expect($params['score']['required'])->toBeTrue();
        expect($params['reason']['required'])->toBeFalse();
    }

    public function test_it_updates_lead_score_successfully(): void
    {
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'lead_score' => 50,
        ]);

        $input = new ToolInputDTO(
            toolName: 'update_lead_score',
            parameters: [
                'negotiation_id' => $negotiation->id,
                'score' => 85,
                'reason' => 'High engagement',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result)->toBeInstanceOf(ToolResultDTO::class);
        expect($result->success)->toBeTrue();
        expect($result->data['previous_score'])->toBe(50);
        expect($result->data['new_score'])->toBe(85);

        $negotiation->refresh();
        expect($negotiation->lead_score)->toBe(85);
    }

    public function test_it_fails_when_negotiation_not_found(): void
    {
        $input = new ToolInputDTO(
            toolName: 'update_lead_score',
            parameters: [
                'negotiation_id' => '00000000-0000-0000-0000-000000099999',
                'score' => 85,
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('not found');
    }

    public function test_it_validates_score_range(): void
    {
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Score above 100
        $input = new ToolInputDTO(
            toolName: 'update_lead_score',
            parameters: [
                'negotiation_id' => $negotiation->id,
                'score' => 150,
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('0 and 100');
    }

    public function test_it_validates_score_minimum(): void
    {
        $negotiation = CRMNegotiation::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $input = new ToolInputDTO(
            toolName: 'update_lead_score',
            parameters: [
                'negotiation_id' => $negotiation->id,
                'score' => -10,
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('0 and 100');
    }
}
