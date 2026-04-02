<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Tools\CloseTicketTool;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class CloseTicketToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CloseTicketTool $tool;

    private PlatformTenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new CloseTicketTool;
        $this->tenant = PlatformTenant::factory()->create();
    }

    public function test_it_implements_ai_tool_interface(): void
    {
        expect($this->tool)->toBeInstanceOf(AiToolInterface::class);
    }

    public function test_it_has_correct_name(): void
    {
        expect($this->tool->getName())->toBe('close_ticket');
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

        expect($params)->toHaveKeys(['ticket_id', 'reason', 'summary']);
        expect($params['ticket_id']['required'])->toBeTrue();
        expect($params['reason']['required'])->toBeTrue();
        expect($params['summary']['required'])->toBeFalse();
    }

    public function test_it_closes_ticket_successfully(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
        ]);

        $input = new ToolInputDTO(
            toolName: 'close_ticket',
            parameters: [
                'ticket_id' => $ticket->id,
                'reason' => 'resolved',
                'summary' => 'Issue was resolved via FAQ',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result)->toBeInstanceOf(ToolResultDTO::class);
        expect($result->success)->toBeTrue();
        expect($result->data['status'])->toBe('closed');

        $ticket->refresh();
        expect($ticket->status)->toBe('closed');
        expect($ticket->close_reason)->toBe('resolved');
    }

    public function test_it_fails_when_ticket_not_found(): void
    {
        $input = new ToolInputDTO(
            toolName: 'close_ticket',
            parameters: [
                'ticket_id' => '00000000-0000-0000-0000-000000099999',
                'reason' => 'resolved',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('not found');
    }

    public function test_it_fails_when_ticket_already_closed(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'closed',
        ]);

        $input = new ToolInputDTO(
            toolName: 'close_ticket',
            parameters: [
                'ticket_id' => $ticket->id,
                'reason' => 'resolved',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('already closed');
    }
}
