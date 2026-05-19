<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentFile;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Services\AiAgentDelegationService;
use Domain\Ai\Services\AiAgentToolPermissionService;
use Domain\Ai\Services\AiPromptResolverService;
use Domain\Ai\Services\AiRagService;
use Domain\Ai\Services\AutopilotRunStreamPublisher;
use Domain\Ai\Services\ToolDispatcherService;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Controller interno para endpoints de IA (requer API key interna).
 *
 * Fornece acesso a contexto de tickets, prompts resolvidos,
 * ferramentas de agentes e execucao de delegacao para o gateway.
 *
 * @category Controllers
 */
final class InternalAiController extends BaseController
{
    public function __construct(
        private readonly ToolDispatcherService $toolDispatcher,
        private readonly AiPromptResolverService $promptResolver,
        private readonly AiRagService $ragService,
        private readonly AiAgentDelegationService $delegationService,
        private readonly AiAgentToolPermissionService $agentToolPermissionService,
        private readonly AutopilotRunStreamPublisher $streamPublisher,
    ) {
        $this->middleware('internal.api.key');
    }

    /**
     * Obter contexto de um ticket para processamento de IA.
     *
     * @param  string  $ticketId  UUID do ticket.
     * @return JsonResponse Contexto do ticket (tenant_id, status, subject).
     */
    public function context(string $ticketId): JsonResponse
    {
        $cacheKey = sprintf('internal:ai:context:%s', $ticketId);

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($ticketId): array {
            $ticket = ChatTicket::query()->with('extended')->findOrFail($ticketId);

            return [
                'ticket_id' => (string) $ticket->id,
                'tenant_id' => (string) $ticket->tenant_id,
                'status' => (string) $ticket->status,
                'subject' => (string) ($ticket->subject ?? ''),
            ];
        });

        return $this->success($payload);
    }

    /**
     * Obter prompt resolvido para um tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @return JsonResponse Prompt resolvido com componentes.
     */
    public function prompt(string $tenantId): JsonResponse
    {
        $cacheKey = sprintf('internal:ai:prompt:%s', $tenantId);

        $payload = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($tenantId): array {
            $tenant = PlatformTenant::query()->findOrFail($tenantId);

            return [
                'tenant_id' => $tenantId,
                'prompt' => $this->promptResolver->resolve($tenant),
            ];
        });

        return $this->success($payload);
    }

    /**
     * Obter definicoes de ferramentas disponiveis para um agente.
     *
     * Lê permissões do pivot ai_agent_tools via AiAgentToolPermissionService.
     *
     * @param  string  $agentId  UUID do agente.
     * @return JsonResponse Lista de ferramentas do agente.
     */
    public function tools(string $agentId): JsonResponse
    {
        $cacheKey = sprintf('internal:ai:tools:%s', $agentId);

        $payload = Cache::remember($cacheKey, now()->addHour(), function () use ($agentId): array {
            $agent = AiAgent::query()->findOrFail($agentId);

            $toolNames = $this->agentToolPermissionService->toolNamesForAgent(
                (string) $agent->tenant_id,
                (string) $agent->id,
            );

            $definitions = $this->toolDispatcher->getToolDefinitions(
                (string) $agent->tenant_id,
                (string) $agent->id,
            );

            return [
                'agent_id' => $agentId,
                'tools' => $definitions,
            ];
        });

        return $this->success($payload);
    }

    /**
     * Executar uma ferramenta especifica.
     *
     * Exige context.agent_id para validação de permissão via ai_agent_tools.
     *
     * @param  Request  $request  Solicitacao com parameters e context.
     * @param  string  $toolName  Nome da ferramenta a executar.
     * @return JsonResponse Resultado da execucao da ferramenta.
     */
    public function executeTool(Request $request, string $toolName): JsonResponse
    {
        $validated = $request->validate([
            'parameters' => ['nullable', 'array'],
            'context' => ['required', 'array'],
        ]);

        $context = (array) $validated['context'];

        if (! isset($context['agent_id']) || (string) $context['agent_id'] === '') {
            return $this->error(
                'Agent context not informed. Required: context.agent_id',
                422,
                ['reason' => 'agent_id_missing'],
            );
        }

        $result = $this->toolDispatcher->dispatch(
            toolName: $toolName,
            parameters: (array) ($validated['parameters'] ?? []),
            context: $context,
        );

        return $this->success($result->toArray());
    }

    /**
     * Obter status de uma execucao (run) do autopilot.
     *
     * @param  Request  $request  Solicitacao com tenant_id opcional via query.
     * @param  string  $runId  UUID da execucao.
     * @return JsonResponse Status detalhado da run.
     */
    public function runStatus(Request $request, string $runId): JsonResponse
    {
        $tenantId = (string) $request->query('tenant_id', '');

        $run = AiAutopilotRun::query()
            ->when($tenantId !== '', fn ($query) => $query->where('tenant_id', $tenantId))
            ->findOrFail($runId);

        $inputContext = is_array($run->input_context) ? $run->input_context : [];

        return $this->success([
            'id' => (string) $run->id,
            'tenant_id' => (string) $run->tenant_id,
            'status' => (string) $run->status,
            'parent_run_id' => $run->parent_run_id !== null ? (string) $run->parent_run_id : null,
            'delegation_depth' => (int) ($run->delegation_depth ?? 0),
            'agent_id' => (string) ($inputContext['agent_id'] ?? ''),
            'output' => is_array($run->output) ? $run->output : null,
            'started_at' => optional($run->started_at)?->toIso8601String(),
            'completed_at' => optional($run->completed_at)?->toIso8601String(),
        ]);
    }

    /**
     * Buscar na base de conhecimento (RAG).
     *
     * @param  Request  $request  Solicitacao com tenant_id, query e k (limite).
     * @return JsonResponse Resultados da busca vetorial.
     */
    public function knowledgeSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'query' => ['required', 'string', 'min:2'],
            'k' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $results = $this->ragService->search(
            query: (string) $validated['query'],
            tenantId: (string) $validated['tenant_id'],
            limit: (int) ($validated['k'] ?? 5),
        );

        return $this->success([
            'results' => $results,
        ]);
    }

    /**
     * Listar agentes delegaveis a partir de um agente origem.
     *
     * @param  Request  $request  tenant_id e agent_id via query string.
     * @return JsonResponse Lista de agentes com regra de delegacao ativa.
     */
    public function availableAgents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'agent_id' => ['required', 'uuid'],
        ]);

        $tenantId = (string) $validated['tenant_id'];
        $agentId = (string) $validated['agent_id'];

        $cacheKey = sprintf('internal:ai:available-agents:%s:%s', $tenantId, $agentId);

        $payload = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($tenantId, $agentId): array {
            $agents = AiAgent::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereHas('targetDelegations', static function ($query) use ($tenantId, $agentId): void {
                    $query->where('tenant_id', $tenantId)
                        ->where('source_agent_id', $agentId)
                        ->where('is_active', true);
                })
                ->get(['id', 'name']);

            return [
                'agents' => $agents
                    ->map(static fn (AiAgent $agent): array => [
                        'id' => (string) $agent->id,
                        'name' => (string) $agent->name,
                    ])
                    ->values()
                    ->all(),
            ];
        });

        return $this->success($payload);
    }

    /**
     * Delegar uma run para outro agente (corrente de delegacao).
     *
     * @param  Request  $request  Dados da delegacao (tenant_id, parent_run_id, target_agent_id, etc).
     * @return JsonResponse child_run_id e status da delegacao.
     */
    public function delegateRun(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'parent_run_id' => ['required', 'uuid'],
            'target_agent_id' => ['required', 'string', 'min:1'],
            'delegation_stack' => ['required', 'array'],
            'delegation_stack.*' => ['string'],
            'input_context' => ['required', 'array'],
        ]);

        $tenantId = (string) $validated['tenant_id'];
        $parentRunId = (string) $validated['parent_run_id'];
        $rawTargetInput = (string) $validated['target_agent_id'];
        $delegationStack = array_values(array_map(static fn (mixed $item): string => (string) $item, (array) $validated['delegation_stack']));

        // Verificação antecipada de delegação circular para entradas UUID
        if (Str::isUuid($rawTargetInput) && in_array($rawTargetInput, $delegationStack, true)) {
            return response()->json([
                'error' => 'circular_delegation',
            ], 422);
        }

        $targetAgentQuery = AiAgent::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);

        $targetAgent = Str::isUuid($rawTargetInput)
            ? $targetAgentQuery->find($rawTargetInput)
            : $targetAgentQuery->whereRaw('LOWER(name) = ?', [mb_strtolower($rawTargetInput)])->first();

        if (! $targetAgent instanceof AiAgent) {
            return $this->error("Target agent not found or inactive: {$rawTargetInput}", 422);
        }

        $targetAgentId = (string) $targetAgent->id;

        // Verificação antecipada de delegação circular pelo UUID resolvido
        if (in_array($targetAgentId, $delegationStack, true)) {
            return response()->json([
                'error' => 'circular_delegation',
            ], 422);
        }

        $parentRun = AiAutopilotRun::query()
            ->where('tenant_id', $tenantId)
            ->find($parentRunId);

        if (! $parentRun instanceof AiAutopilotRun) {
            return $this->error('Parent run not found.', 404);
        }

        $parentContext = is_array($parentRun->input_context) ? $parentRun->input_context : [];
        $sourceAgentId = (string) ($parentContext['agent_id'] ?? '');

        if ($sourceAgentId === '') {
            return $this->error('Parent run does not define source agent.', 422);
        }

        $result = $this->delegationService->delegate(
            tenantId: $tenantId,
            parentRunId: $parentRunId,
            sourceAgentId: $sourceAgentId,
            targetAgentId: $targetAgentId,
            payload: [
                'delegation_stack' => $delegationStack,
                'input_context' => (array) $validated['input_context'],
            ],
            dispatchExecutionJob: false,
        );

        if (! ($result['success'] ?? false)) {
            if (($result['message'] ?? '') === 'Circular delegation detected.') {
                return response()->json([
                    'error' => 'circular_delegation',
                ], 422);
            }

            return $this->error((string) ($result['message'] ?? 'Delegation failed.'), 422);
        }

        $childRunId = (string) ($result['child_run_id'] ?? '');

        $promptComponents = $this->resolvePromptComponents($tenantId);
        $agentFilePrompts = $this->resolveAgentFilePrompts($targetAgent);
        $inputContext = (array) $validated['input_context'];
        $modelId = is_string($targetAgent->model_id) && $targetAgent->model_id !== ''
            ? $targetAgent->model_id
            : null;

        if ($modelId === null) {
            Log::warning('[InternalAiController] Delegating run without explicit model_id', [
                'tenant_id' => $tenantId,
                'parent_run_id' => $parentRunId,
                'child_run_id' => $childRunId,
                'target_agent_id' => (string) $targetAgent->id,
            ]);
        }

        $streamPayload = [
            'event' => 'ai.run.request',
            'run_id' => $childRunId,
            'tenant_id' => $tenantId,
            'agent_id' => (string) $targetAgent->id,
            'parent_run_id' => $parentRunId,
            'source' => 'delegation_internal',
            'trigger_type' => 'delegation',
            'ticket_id' => (string) ($inputContext['ticket_id'] ?? ''),
            'input_text' => (string) ($inputContext['body'] ?? ''),
            'streaming_enabled' => true,
            'max_tokens' => (int) config('ai.autopilot.max_tokens', 800),
            'max_tool_iterations' => (int) config('ai.autopilot.max_tool_iterations', 5),
            'run_token_budget' => (int) config('ai.autopilot.run_token_budget', 3000),
            'compact_tool_results' => (bool) config('ai.autopilot.compact_tool_results', true) ? 'true' : 'false',
            'super_prompt' => $promptComponents['super_prompt'],
            'segment_prompt' => $promptComponents['segment_prompt'],
            'plan_prompt' => $promptComponents['plan_prompt'],
            'agent_system_prompt' => (string) ($targetAgent->system_prompt ?? ''),
            'agent_file_prompts' => $agentFilePrompts,
            'delegation_stack' => [...$delegationStack, $targetAgentId],
            'requested_at' => now()->toIso8601String(),
            'prompt' => $this->buildPromptSnapshot($promptComponents, $targetAgent, $agentFilePrompts),
            'context' => $inputContext,
            'tools' => $this->resolveAgentToolsSnapshot($targetAgent),
            'hydrated_at' => now()->toIso8601String(),
        ];

        if ($modelId !== null) {
            $streamPayload['model'] = $modelId;
        }

        $this->streamPublisher->publish($streamPayload);

        return $this->accepted([
            'child_run_id' => $childRunId,
            'status' => 'queued',
        ]);
    }

    /**
     * @return array{super_prompt: string, segment_prompt: string, plan_prompt: string}
     */
    private function resolvePromptComponents(string $tenantId): array
    {
        $defaults = [
            'super_prompt' => '',
            'segment_prompt' => '',
            'plan_prompt' => '',
        ];

        $tenant = PlatformTenant::query()->find($tenantId);
        if (! $tenant instanceof PlatformTenant) {
            return $defaults;
        }

        $components = $this->promptResolver->getComponents($tenant);

        return [
            'super_prompt' => (string) ($components['master'] ?? ''),
            'segment_prompt' => (string) ($components['segment'] ?? ''),
            'plan_prompt' => (string) ($components['plan'] ?? ''),
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveAgentFilePrompts(AiAgent $agent): array
    {
        $files = AiAgentFile::query()
            ->where('tenant_id', (string) $agent->tenant_id)
            ->where('agent_id', (string) $agent->id)
            ->orderBy('slug')
            ->get(['slug', 'content']);

        return $files
            ->filter(static fn (AiAgentFile $file): bool => trim((string) $file->content) !== '')
            ->map(static fn (AiAgentFile $file): string => sprintf('[%s]'."\n".'%s', (string) $file->slug, (string) $file->content))
            ->values()
            ->all();
    }

    /**
     * @param  array{super_prompt: string, segment_prompt: string, plan_prompt: string}  $promptComponents
     * @param  list<string>  $agentFilePrompts
     */
    private function buildPromptSnapshot(array $promptComponents, AiAgent $agent, array $agentFilePrompts): string
    {
        return collect([
            (string) ($promptComponents['super_prompt'] ?? ''),
            (string) ($promptComponents['segment_prompt'] ?? ''),
            (string) ($promptComponents['plan_prompt'] ?? ''),
            (string) ($agent->system_prompt ?? ''),
            ...$agentFilePrompts,
        ])
            ->map(static fn (string $chunk): string => trim($chunk))
            ->filter(static fn (string $chunk): bool => $chunk !== '')
            ->unique()
            ->values()
            ->implode("\n\n");
    }

    /**
     * @return list<string>
     */
    private function resolveAgentToolsSnapshot(AiAgent $agent): array
    {
        return $this->agentToolPermissionService->toolNamesForAgent(
            (string) $agent->tenant_id,
            (string) $agent->id,
        );
    }
}
