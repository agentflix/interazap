<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Carbon\Carbon;
use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Services\AiToolEntityResolver;
use Domain\CRM\Models\CRMNegotiationTask;
use Illuminate\Support\Str;

/**
 * Ferramenta de IA para criar tarefas de acompanhamento vinculadas a negociações.
 *
 * Input esperado: title (obrigatório); negotiation_id, ticket_id, contact_id, due_date e assigned_to opcionais.
 * Output produzido: task_id, negotiation_id e due_date da tarefa criada.
 * Quando usar: agendar callbacks, reuniões ou lembretes de ações de acompanhamento.
 */
class CreateTaskTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolEntityResolver $entityResolver,
    ) {}

    /** Executa a criação da tarefa de acompanhamento. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $title = $input->parameters['title'] ?? '';
        $description = $input->parameters['description'] ?? null;
        $dueDate = $input->parameters['due_date'] ?? null;
        $negotiationId = $input->parameters['negotiation_id'] ?? null;
        $ticketId = $input->parameters['ticket_id'] ?? $input->context['ticket_id'] ?? null;
        $contactId = $input->parameters['contact_id'] ?? $input->context['contact_id'] ?? null;
        $assignedTo = $input->parameters['assigned_to'] ?? null;
        $tenantId = $input->context['tenant_id'] ?? null;

        // Validate title
        if (empty(trim($title))) {
            return ToolResultDTO::failure('Title is required');
        }

        $negotiation = $this->entityResolver->resolveNegotiation(
            (string) $tenantId,
            $input->parameters,
            is_string($ticketId) ? $ticketId : null,
            is_string($contactId) ? $contactId : null,
        );

        if (! $negotiation) {
            return ToolResultDTO::failure('Negotiation not found', [
                'error_code' => 'negotiation_not_found',
                'recoverable' => true,
                'hint' => 'Create or identify a negotiation before creating a negotiation task, or send a message to the customer and transfer to a human.',
                'provided_negotiation_id' => is_string($negotiationId) ? $negotiationId : null,
                'ticket_id' => is_string($ticketId) ? $ticketId : null,
                'contact_id' => is_string($contactId) ? $contactId : null,
            ]);
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

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::CREATE_TASK;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Creates a follow-up task associated with a negotiation. Use to schedule callbacks, meetings, or reminder actions.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
     *
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
                'required' => false,
                'description' => 'UUID of the negotiation. Optional when ticket_id/contact_id can resolve an open negotiation.',
            ],
            'ticket_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Ticket UUID used to resolve the contact and active negotiation.',
            ],
            'contact_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Contact UUID used to resolve an active negotiation.',
            ],
            'assigned_to' => [
                'type' => 'string',
                'required' => false,
                'description' => 'UUID of the user to assign the task to',
            ],
        ];
    }
}
