<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Tools\CreateTaskTool;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMNegotiation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group tools
 */
class CreateTaskToolTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CreateTaskTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = app(CreateTaskTool::class);
    }

    public function test_it_implements_ai_tool_interface(): void
    {
        expect($this->tool)->toBeInstanceOf(AiToolInterface::class);
    }

    public function test_it_has_correct_name(): void
    {
        expect($this->tool->getName())->toBe('create_task');
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

        expect($params)->toHaveKeys(['title', 'description', 'due_date', 'negotiation_id', 'assigned_to']);
        expect($params['title']['required'])->toBeTrue();
        expect($params['negotiation_id']['required'])->toBeFalse();
        expect($params['due_date']['required'])->toBeFalse();
        expect($params['assigned_to']['required'])->toBeFalse();
    }

    public function test_it_creates_task_successfully(): void
    {
        $negotiation = CRMNegotiation::factory()->create();

        $input = new ToolInputDTO(
            toolName: 'create_task',
            parameters: [
                'title' => 'Follow up call',
                'description' => 'Call to discuss proposal',
                'due_date' => now()->addDays(2)->toIso8601String(),
                'negotiation_id' => $negotiation->id,
            ],
            context: ['tenant_id' => $negotiation->tenant_id],
        );

        $result = $this->tool->handle($input);

        expect($result)->toBeInstanceOf(ToolResultDTO::class);
        expect($result->success)->toBeTrue();
        expect($result->data)->toHaveKey('task_id');

        $task = \Domain\CRM\Models\CRMNegotiationTask::query()->find($result->data['task_id']);
        expect($task)->not->toBeNull();
        expect($task->title)->toBe('Follow up call');
        expect($task->description)->toBe('Call to discuss proposal');
        expect($task->crm_negotiation_id)->toBe($negotiation->id);
    }

    public function test_it_creates_task_without_due_date(): void
    {
        $negotiation = CRMNegotiation::factory()->create();

        $input = new ToolInputDTO(
            toolName: 'create_task',
            parameters: [
                'title' => 'General task',
                'negotiation_id' => $negotiation->id,
            ],
            context: ['tenant_id' => $negotiation->tenant_id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeTrue();
        expect($result->data)->toHaveKey('task_id');
    }

    public function test_it_resolves_negotiation_from_ticket_when_negotiation_id_is_not_available(): void
    {
        $negotiation = CRMNegotiation::factory()->create();
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $negotiation->tenant_id,
            'contact_id' => $negotiation->crm_contact_id,
        ]);

        $input = new ToolInputDTO(
            toolName: 'create_task',
            parameters: [
                'title' => 'Follow up from ticket',
                'ticket_id' => (string) $ticket->id,
            ],
            context: ['tenant_id' => (string) $negotiation->tenant_id],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeTrue();
        expect($result->data['negotiation_id'])->toBe((string) $negotiation->id);
    }

    public function test_it_fails_when_title_is_empty(): void
    {
        $negotiation = CRMNegotiation::factory()->create();

        $input = new ToolInputDTO(
            toolName: 'create_task',
            parameters: [
                'title' => '',
                'negotiation_id' => $negotiation->id,
            ],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('Title');
    }

    public function test_it_fails_when_due_date_is_invalid(): void
    {
        $negotiation = CRMNegotiation::factory()->create();

        $input = new ToolInputDTO(
            toolName: 'create_task',
            parameters: [
                'title' => 'Task',
                'negotiation_id' => $negotiation->id,
                'due_date' => 'invalid-date',
            ],
            context: [
                'tenant_id' => $negotiation->tenant_id,
            ],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('date');
    }

    public function test_it_fails_when_negotiation_not_found(): void
    {
        $input = new ToolInputDTO(
            toolName: 'create_task',
            parameters: [
                'title' => 'Task',
                'negotiation_id' => '00000000-0000-0000-0000-000000099999',
            ],
        );

        $result = $this->tool->handle($input);

        expect($result->success)->toBeFalse();
        expect($result->message)->toContain('Negotiation not found');
        expect($result->data['error_code'])->toBe('negotiation_not_found');
        expect($result->data['recoverable'])->toBeTrue();
    }
}
