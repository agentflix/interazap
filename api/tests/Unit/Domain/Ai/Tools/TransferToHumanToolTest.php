<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Tools\TransferToHumanTool;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class TransferToHumanToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TransferToHumanTool $tool;

    private PlatformTenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new TransferToHumanTool;
        $this->tenant = PlatformTenant::factory()->create();
    }

    public function test_it_implements_ai_tool_interface(): void
    {
        expect($this->tool)->toBeInstanceOf(AiToolInterface::class);
    }

    public function test_it_has_correct_name(): void
    {
        expect($this->tool->getName())->toBe('transfer_to_human');
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

        expect($params)->toHaveKeys(['ticket_id', 'reason', 'priority']);
        expect($params['ticket_id']['required'])->toBeTrue();
        expect($params['reason']['required'])->toBeTrue();
        expect($params['priority']['required'])->toBeFalse();
    }

    public function test_it_transfers_to_human_successfully(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
            'is_bot_active' => true,
        ]);

        $input = new ToolInputDTO(
            toolName: 'transfer_to_human',
            parameters: [
                'ticket_id' => $ticket->id,
                'reason' => 'Customer requested human support',
                'priority' => 'high',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result)->toBeInstanceOf(ToolResultDTO::class);
        expect($result->success)->toBeTrue();

        $ticket->refresh();
        expect($ticket->is_bot_active)->toBeFalse();
        expect($ticket->human_takeover_at)->not->toBeNull();
    }

    public function test_it_fails_when_ticket_not_found(): void
    {
        $input = new ToolInputDTO(
            toolName: 'transfer_to_human',
            parameters: [
                'ticket_id' => '00000000-0000-0000-0000-000000099999',
                'reason' => 'Customer requested',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('not found');
    }

    public function test_it_succeeds_even_when_already_transferred(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
            'is_bot_active' => false,
        ]);

        $input = new ToolInputDTO(
            toolName: 'transfer_to_human',
            parameters: [
                'ticket_id' => $ticket->id,
                'reason' => 'Already transferred but confirming',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        // Idempotent - success even if already transferred
        expect($result->success)->toBeTrue();
    }
}
