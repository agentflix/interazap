<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Tools\ReadTicketTool;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class ReadTicketToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ReadTicketTool $tool;

    private PlatformTenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new ReadTicketTool;
        $this->tenant = PlatformTenant::factory()->create();
    }

    public function test_it_implements_ai_tool_interface(): void
    {
        expect($this->tool)->toBeInstanceOf(AiToolInterface::class);
    }

    public function test_it_has_correct_name(): void
    {
        expect($this->tool->getName())->toBe('read_ticket');
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

        expect($params)->toHaveKeys(['ticket_id', 'include_messages', 'message_limit']);
        expect($params['ticket_id']['required'])->toBeTrue();
        expect($params['include_messages']['required'])->toBeFalse();
        expect($params['message_limit']['required'])->toBeFalse();
    }

    public function test_it_reads_ticket_info_successfully(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
            'subject' => 'Help with order',
            'priority' => 'high',
        ]);

        $input = new ToolInputDTO(
            toolName: 'read_ticket',
            parameters: [
                'ticket_id' => $ticket->id,
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result)->toBeInstanceOf(ToolResultDTO::class);
        expect($result->success)->toBeTrue();
        expect($result->data['ticket']['id'])->toBe($ticket->id);
        expect($result->data['ticket']['status'])->toBe('open');
        expect($result->data['ticket']['subject'])->toBe('Help with order');
    }

    public function test_it_includes_messages_when_requested(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        ChatMessage::factory()->count(3)->create([
            'ticket_id' => $ticket->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $input = new ToolInputDTO(
            toolName: 'read_ticket',
            parameters: [
                'ticket_id' => $ticket->id,
                'include_messages' => true,
                'message_limit' => 10,
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeTrue();
        expect($result->data)->toHaveKey('messages');
        expect($result->data['messages'])->toHaveCount(3);
    }

    public function test_it_limits_messages(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        ChatMessage::factory()->count(10)->create([
            'ticket_id' => $ticket->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $input = new ToolInputDTO(
            toolName: 'read_ticket',
            parameters: [
                'ticket_id' => $ticket->id,
                'include_messages' => true,
                'message_limit' => 5,
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeTrue();
        expect($result->data['messages'])->toHaveCount(5);
    }

    public function test_it_fails_when_ticket_not_found(): void
    {
        $input = new ToolInputDTO(
            toolName: 'read_ticket',
            parameters: [
                'ticket_id' => '00000000-0000-0000-0000-000000099999',
            ],
            context: ['tenant_id' => $this->tenant->id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('not found');
    }
}
