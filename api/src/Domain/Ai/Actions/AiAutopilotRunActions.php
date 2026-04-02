<?php

declare(strict_types=1);

namespace Domain\Ai\Actions;

use Domain\Ai\Contracts\AIServiceInterface;
use Domain\Ai\DTOs\AiAutopilotRunDTO;
use Domain\Ai\Jobs\AiRunExecutionJob;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Models\AiUsageLog;
use Domain\Ai\Services\AiPromptResolverService;
use Domain\Ai\Services\AiSkillExecutorService;
use Domain\Ai\Services\GuardrailEvaluatorService;
use Domain\Ai\Services\ToolDispatcherService;
use Domain\Gateway\DTOs\AI\AICompletionRequest;
use Domain\Gateway\DTOs\AI\AICompletionResponse;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Orquestrador de Execuções e Simulações para Autopilot.
 *
 * Lida com o ciclo de vida de uma execução (Run), desde a inicialização
 * até a chamada de LLM e gravação dos resultados e ações individuais.
 *
 * @category Actions
 */
final class AiAutopilotRunActions
{
    private const int DEFAULT_MAX_TOOL_ITERATIONS = 10;

    private const int DEFAULT_MAX_TOKENS = 800;

    public function __construct(
        private readonly AIServiceInterface $aiService,
        private readonly AiPromptResolverService $promptResolver,
        private readonly ToolDispatcherService $toolDispatcher,
        private readonly GuardrailEvaluatorService $guardrailEvaluator,
        private readonly ?AiSkillExecutorService $skillExecutor = null,
    ) {}

    /**
     * Listar execuções de um tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return LengthAwarePaginator<int, AiAutopilotRun> Paginador com as execuções.
     */
    public function list(string $tenantId): LengthAwarePaginator
    {
        return AiAutopilotRun::query()
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate();
    }

    /**
     * Buscar uma execução específica.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da execução.
     * @return AiAutopilotRun Modelo encontrado.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find(string $tenantId, string $id): AiAutopilotRun
    {
        return AiAutopilotRun::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    public function cancel(string $tenantId, string $id): AiAutopilotRun
    {
        $run = AiAutopilotRun::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        if (in_array((string) $run->status, ['queued', 'running', 'paused'], true)) {
            $run->status = 'cancelled';
            $run->completed_at = now();
            $run->save();
        }

        return $run;
    }

    public function initiate(string $tenantId, AiAutopilotRunDTO $dto): AiAutopilotRun
    {
        $playbookId = $dto->playbookId;

        // SE-001: Try resolve playbook_id from context if not provided (backwards compatibility)
        if ($playbookId === '' && isset($dto->context['playbook']['name'])) {
            $playbookName = (string) $dto->context['playbook']['name'];
            $playbookId = (string) \Domain\Ai\Models\AiAutopilotPlaybook::query()
                ->where('tenant_id', $tenantId)
                ->where('name', $playbookName)
                ->value('id');
        }

        return AiAutopilotRun::query()->create([
            'id' => (string) \Illuminate\Support\Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'playbook_id' => $playbookId !== '' ? $playbookId : null,
            'playbook_version' => 2,
            'status' => 'queued',
            'input_context' => $dto->context,
        ]);
    }

    /**
     * @param  callable(string, array<string, mixed>):void|null  $emitEvent
     */
    public function executeWithEvents(AiAutopilotRun $run, ?callable $emitEvent = null): AiAutopilotRun
    {
        $emit = $emitEvent ?? static function (string $eventType, array $data): void {};

        $run = AiAutopilotRun::query()
            ->where('tenant_id', $run->tenant_id)
            ->findOrFail($run->id);

        if ((string) $run->status === 'cancelled') {
            return $run;
        }

        $run->status = 'running';
        $run->started_at = $run->started_at ?? now();
        $run->save();

        $emit('ai.run.started', [
            'status' => 'running',
            'playbook_id' => $run->playbook_id,
            'started_at' => optional($run->started_at)->toIso8601String(),
        ]);

        try {
            $runtimeContext = $run->input_context ?? [];
            $agentRole = (string) data_get($runtimeContext, 'agent_role', 'general');
            $toolDefinitions = $this->toolDispatcher->getToolDefinitions((string) $run->tenant_id, $agentRole);

            $responsePayload = $this->generateResponse(
                tenantId: (string) $run->tenant_id,
                context: $runtimeContext,
                toolDefinitions: $toolDefinitions,
                run: $run,
                emitEvent: $emit,
            );

            $output = $responsePayload['output'];
            $messages = $responsePayload['messages'];
            $isBlocked = (bool) data_get($output, 'blocked', false);
            $cachedPromptTokens = (int) data_get($output, 'raw.prompt_tokens', 0);

            $run->status = $isBlocked ? 'blocked' : 'completed';
            $run->output = $output;
            $run->cached_prompt_tokens = $cachedPromptTokens;
            $run->completed_at = now();
            $run->save();

            AiUsageLog::query()->create([
                'tenant_id' => (string) $run->tenant_id,
                'model_name' => (string) data_get($output, 'raw.model', 'unknown'),
                'provider' => 'openai',
                'input_tokens' => (int) data_get($output, 'raw.prompt_tokens', 0),
                'output_tokens' => (int) data_get($output, 'raw.completion_tokens', 0),
                'cached_prompt_tokens' => $cachedPromptTokens,
                'feature' => 'autopilot.run',
                'usable_type' => AiAutopilotRun::class,
                'usable_id' => (string) $run->id,
                'metadata' => [
                    'playbook_id' => (string) $run->playbook_id,
                    'status' => (string) $run->status,
                ],
            ]);

            if ($isBlocked) {
                $emit('ai.run.blocked', [
                    'status' => 'blocked',
                    'reason' => (string) data_get($output, 'error', 'Execution blocked by guardrail policy.'),
                    'output' => $output,
                ]);
            } else {
                $emit('ai.run.completed', [
                    'status' => 'completed',
                    'output' => $output,
                    'tokens' => data_get($output, 'raw'),
                ]);
            }

            return $run;
        } catch (\Throwable $exception) {
            $errorMessage = $exception->getMessage();
            $run->status = 'failed';
            $run->output = [
                'response' => 'Execution failed.',
                'error' => $errorMessage,
                'blocked' => false,
                'raw' => [
                    'model' => data_get($run->output, 'raw.model', 'unknown'),
                    'prompt_tokens' => (int) data_get($run->output, 'raw.prompt_tokens', 0),
                    'completion_tokens' => (int) data_get($run->output, 'raw.completion_tokens', 0),
                    'total_tokens' => (int) data_get($run->output, 'raw.total_tokens', 0),
                    'tool_iterations' => (int) data_get($run->output, 'raw.tool_iterations', 0),
                ],
            ];
            $run->completed_at = now();
            $run->save();

            $emit('ai.run.failed', [
                'run_id' => (string) $run->id,
                'tenant_id' => (string) $run->tenant_id,
                'status' => 'failed',
                'error' => $errorMessage,
                'completed_at' => optional($run->completed_at)->toIso8601String(),
            ]);

            return $run;
        }
    }

    public function run(string $tenantId, AiAutopilotRunDTO $dto): AiAutopilotRun
    {
        $run = $this->initiate($tenantId, $dto);

        AiRunExecutionJob::dispatch($run->id);

        return $run;
    }

    /**
     * Gerar resposta via LLM baseada nas instruções do playbook.
     *
     * @param  array<string, mixed>  $context  Contexto de entrada do usuário/sistema.
     * @param  array<int, array<string, mixed>>  $toolDefinitions
     * @return array{output: array<string, mixed>, messages: array<int, array<string, mixed>>}
     */
    private function generateResponse(
        string $tenantId,
        array $context,
        array $toolDefinitions,
        AiAutopilotRun $run,
        callable $emitEvent,
    ): array {
        $tenant = PlatformTenant::query()->findOrFail($tenantId);
        $playbookObjective = trim((string) data_get($context, 'playbook.objective', ''));
        $playbookInstructions = trim((string) data_get($context, 'playbook.instructions', ''));
        $agentId = (string) data_get($context, 'agent_id', '');
        $agentRole = (string) data_get($context, 'agent_role', 'general');
        $skillExecutor = $this->skillExecutor ?? app(AiSkillExecutorService::class);

        $preSkillTrace = [];
        if ($agentId !== '') {
            $preSkillResult = $skillExecutor->executePreRun($tenantId, $agentId, $context);
            $context = $preSkillResult['context'];
            $preSkillTrace = $preSkillResult['trace'];
        }

        $runtimeContext = [
            'playbook' => [
                'name' => (string) data_get($context, 'playbook.name', 'runtime-v2'),
                'step_count' => count((array) data_get($context, 'playbook.steps', [])),
                'objective' => $playbookObjective,
                'instructions' => $playbookInstructions,
            ],
            'context' => $context,
        ];

        $runtimeContextJson = json_encode($runtimeContext, JSON_THROW_ON_ERROR);
        $systemPrompt = $this->promptResolver->resolve($tenant);

        if ($playbookObjective !== '' || $playbookInstructions !== '') {
            $systemPrompt .= "\n\n[PLAYBOOK CONTEXT]";

            if ($playbookObjective !== '') {
                $systemPrompt .= "\nObjective: {$playbookObjective}";
            }

            if ($playbookInstructions !== '') {
                $systemPrompt .= "\nInstructions:\n{$playbookInstructions}";
            }
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $runtimeContextJson],
        ];
        $toolTrace = [];
        $tokenTotals = [
            'prompt' => 0,
            'completion' => 0,
            'total' => 0,
        ];

        $addTokens = static function (AICompletionResponse $response) use (&$tokenTotals): void {
            $tokenTotals['prompt'] += $response->promptTokens;
            $tokenTotals['completion'] += $response->completionTokens;
            $tokenTotals['total'] += $response->totalTokens;
        };

        $emitEvent('ai.run.thinking', [
            'status' => 'running',
            'step' => 'Chamando LLM...',
            'iteration' => 0,
        ]);
        $response = $this->completeWithTools($messages, $toolDefinitions);
        $addTokens($response);
        $iteration = 0;

        while ($response->hasToolCalls() && $iteration < $this->resolveMaxToolIterations()) {
            $iteration++;

            foreach ($response->toolCalls as $toolCall) {
                $toolName = (string) data_get($toolCall, 'name', '');
                $arguments = data_get($toolCall, 'arguments', []);
                if (is_string($arguments)) {
                    $decoded = json_decode($arguments, true);
                    $arguments = is_array($decoded) ? $decoded : [];
                }

                $guardrailResult = $this->guardrailEvaluator->evaluate(
                    tenantId: $tenantId,
                    actionType: 'tool_call',
                    input: [
                        'tool' => $toolName,
                        'args' => $arguments,
                    ],
                );

                if ($guardrailResult->blocked) {
                    return [
                        'output' => [
                            'response' => 'Execution blocked by guardrail policy.',
                            'error' => 'Execution blocked by guardrail policy.',
                            'blocked' => true,
                            'raw' => [
                                'model' => $response->model,
                                'prompt_tokens' => $tokenTotals['prompt'],
                                'completion_tokens' => $tokenTotals['completion'],
                                'total_tokens' => $tokenTotals['total'],
                                'finish_reason' => $response->finishReason?->value,
                                'tool_iterations' => $iteration,
                            ],
                            'tool_trace' => [[
                                'tool' => $toolName,
                                'args' => $arguments,
                                'blocked' => true,
                                'guardrail_evaluations' => $guardrailResult->evaluations,
                            ]],
                        ],
                        'messages' => $messages,
                    ];
                }

                $emitEvent('ai.run.tool_call', [
                    'status' => 'running',
                    'tool_name' => $toolName,
                    'tool_args' => is_array($arguments) ? $arguments : [],
                    'iteration' => $iteration,
                ]);

                $result = $this->toolDispatcher->dispatch(
                    toolName: $toolName,
                    parameters: is_array($arguments) ? $arguments : [],
                    context: array_merge($context, [
                        'tenant_id' => $tenantId,
                        'current_run_id' => (string) $run->id,
                        'delegation_depth' => (int) ($run->delegation_depth ?? 0),
                        'agent_role' => $agentRole,
                    ]),
                );

                $toolTrace[] = [
                    'tool' => $toolName,
                    'args' => is_array($arguments) ? $arguments : [],
                    'result' => $result->toArray(),
                    'success' => $result->success,
                    'iteration' => $iteration,
                ];

                $emitEvent('ai.run.tool_result', [
                    'status' => 'running',
                    'tool_name' => $toolName,
                    'result' => $result->toArray(),
                    'success' => $result->success,
                    'iteration' => $iteration,
                ]);

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $response->content,
                ];
                $messages[] = [
                    'role' => 'tool',
                    'name' => $toolName,
                    'content' => json_encode($result->toArray(), JSON_THROW_ON_ERROR),
                ];
            }

            $emitEvent('ai.run.thinking', [
                'status' => 'running',
                'step' => 'Chamando LLM...',
                'iteration' => $iteration,
            ]);
            $response = $this->completeWithTools($messages, $toolDefinitions);
            $addTokens($response);
        }

        $finalGuardrailResult = $this->guardrailEvaluator->evaluate(
            tenantId: $tenantId,
            actionType: 'llm_response',
            input: ['messages' => $messages],
            output: ['response' => $response->content],
        );

        $isBlockedAfterResponse = $finalGuardrailResult->blocked;

        $output = [
            'response' => $isBlockedAfterResponse ? 'Execution blocked by guardrail policy.' : $response->content,
            'error' => $isBlockedAfterResponse ? 'Execution blocked by guardrail policy.' : null,
            'blocked' => $isBlockedAfterResponse,
            'guardrail_result' => $finalGuardrailResult->evaluations,
            'raw' => [
                'model' => $response->model,
                'prompt_tokens' => $tokenTotals['prompt'],
                'completion_tokens' => $tokenTotals['completion'],
                'total_tokens' => $tokenTotals['total'],
                'finish_reason' => $response->finishReason?->value,
                'tool_iterations' => $iteration,
            ],
            'tool_trace' => $toolTrace,
            'skill_trace' => $preSkillTrace,
        ];

        if ($agentId !== '') {
            $postSkillResult = $skillExecutor->executePostRun($tenantId, $agentId, $output);
            $output = $postSkillResult['output'];
            $output['skill_trace'] = array_merge($preSkillTrace, $postSkillResult['trace']);
        }

        return [
            'output' => $output,
            'messages' => $messages,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $toolDefinitions
     */
    private function completeWithTools(array $messages, array $toolDefinitions): AICompletionResponse
    {
        $request = AICompletionRequest::create($messages)
            ->withMaxTokens($this->resolveMaxTokens());
        if ($toolDefinitions !== []) {
            $request = $request->withTools($toolDefinitions);
        }

        return $this->aiService->complete($request);
    }

    private function resolveMaxTokens(): int
    {
        return (int) config('ai.autopilot.max_tokens', self::DEFAULT_MAX_TOKENS);
    }

    private function resolveMaxToolIterations(): int
    {
        return (int) config('ai.autopilot.max_tool_iterations', self::DEFAULT_MAX_TOOL_ITERATIONS);
    }
}
