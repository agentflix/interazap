<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Carbon\Carbon;
use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationTask;
use Illuminate\Support\Str;

/**
 * Ferramenta para criar tarefas em negociações.
 */
class CreateTaskTool implements AiToolInterface
{
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $title = $input->parameters['title'] ?? '';
        $description = $input->parameters['description'] ?? null;
        $dueDate = $input->parameters['due_date'] ?? null;
        $negotiationId = $input->parameters['negotiation_id'] ?? null;
        $assignedTo = $input->parameters['assigned_to'] ?? null;
        $tenantId = $input->context['tenant_id'] ?? null;

        // Validate title
        if (empty(trim($title))) {
            return ToolResultDTO::failure('Title is required');
        }

        // Tenant isolation: only access negotiations from the same tenant
        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->find($negotiationId);
        if (! $negotiation) {
            return ToolResultDTO::failure('Negotiation not found');
        }

        // Parse due date if provided
        $parsedDueDate = null;
        if ($dueDate) {
            try {
                $parsedDueDate = Carbon::parse($dueDate);
            } catch (\Exception) {
                return ToolResultDTO::failure('Invalid date format');
            }
        }

        // Create task
        $task = CRMNegotiationTask::create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId ?? $negotiation->tenant_id,
            'crm_negotiation_id' => $negotiation->id,
            'auth_user_id' => $assignedTo,
            'title' => trim($title),
            'description' => $description,
            'due_date' => $parsedDueDate,
            'status' => 'pending',
        ]);

        return ToolResultDTO::success(
            message: 'Task created successfully',
            data: [
                'task_id' => $task->id,
                'negotiation_id' => $negotiation->id,
                'title' => $task->title,
                'due_date' => $parsedDueDate?->toIso8601String(),
            ]
        );
    }

    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::CREATE_TASK;
    }

    public function getDescription(): string
    {
        return 'Creates a follow-up task associated with a negotiation. Use to schedule callbacks, meetings, or reminder actions.';
    }

    /**
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'title' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Title of the task',
            ],
            'description' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Detailed description of the task',
            ],
            'due_date' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Due date in ISO 8601 format',
            ],
            'negotiation_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of the negotiation',
            ],
            'assigned_to' => [
                'type' => 'string',
                'required' => false,
                'description' => 'UUID of the user to assign the task to',
            ],
        ];
    }
}
