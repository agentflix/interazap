<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Services\AiAgentDelegationService;

/**
 * Delegates execution to a target agent by creating a child run.
 */
final class DelegateToAgentTool implements AiToolInterface
{
    public function __construct(private readonly AiAgentDelegationService $delegationService) {}

    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $tenantId = (string) ($input->context['tenant_id'] ?? '');
        $parentRunId = (string) ($input->context['current_run_id'] ?? '');
        $sourceAgentId = (string) ($input->context['agent_id'] ?? '');
        $targetAgentId = (string) ($input->parameters['target_agent_id'] ?? '');

        if ($tenantId === '' || $parentRunId === '' || $sourceAgentId === '' || $targetAgentId === '') {
            return ToolResultDTO::failure('Missing required delegation context/parameters.');
        }

        $result = $this->delegationService->delegate(
            tenantId: $tenantId,
            parentRunId: $parentRunId,
            sourceAgentId: $sourceAgentId,
            targetAgentId: $targetAgentId,
            payload: $input->parameters,
        );

        if (! $result['success']) {
            return ToolResultDTO::failure((string) $result['message'], $result);
        }

        return ToolResultDTO::success((string) $result['message'], $result);
    }

    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::DELEGATE_TO_AGENT;
    }

    public function getDescription(): string
    {
        return 'Delegates the current execution to another configured agent and creates a child run.';
    }

    public function getParameters(): array
    {
        return [
            'target_agent_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Name or UUID of the target agent (e.g. "Vendas", "Suporte", "Qualificacao", "Reativacao").',
            ],
            'target_playbook_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Optional target playbook UUID for child run.',
            ],
            'return_after' => [
                'type' => 'boolean',
                'required' => false,
                'description' => 'If true, parent waits and consumes child result.',
            ],
        ];
    }
}
