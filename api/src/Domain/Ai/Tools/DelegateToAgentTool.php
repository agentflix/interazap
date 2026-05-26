<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Services\AiAgentDelegationService;

/**
 * Ferramenta de IA para delegar a execução a outro agente criando uma child run.
 *
 * Input esperado: target_agent_id (nome ou UUID do agente de destino); target_playbook_id e return_after opcionais.
 * Output produzido: child_run_id e status da delegação.
 * Quando usar: a conversa exige especialização de outro agente (ex.: Vendas, Suporte, Qualificação).
 */
final class DelegateToAgentTool implements AiToolInterface
{
    public function __construct(private readonly AiAgentDelegationService $delegationService) {}

    /** Executa a delegação criando uma child run para o agente de destino. */
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

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::DELEGATE_TO_AGENT;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Delegates the current execution to another configured agent and creates a child run.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
     *
     * @return array<string, array<string, mixed>>
     */
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
