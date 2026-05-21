<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Carbon\Carbon;
use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AiRunFailed;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentFile;
use Domain\Ai\Models\AiAgentTrigger;
use Domain\Ai\Models\AiAutopilotPlaybook;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Observers\AiAgentTriggerObserver;
use Domain\Ai\Services\AiContextBuilderService;
use Domain\Ai\Services\AutopilotRunSnapshotResolver;
use Domain\Ai\Services\AutopilotRunStreamPublisher;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatAiActivityService;
use Domain\Shared\Services\MetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

/**
 * Job assíncrono para execução do dispatcher de Autopilot.
 *
 * Extraído de AutopilotRunDispatcherListener para evitar timeout de PHP
 * no processo síncrono do webhook (max_execution_time = 30s).
 *
 * Este job executa em fila com timeout de 300s, isolado do processo HTTP.
 */
final class DispatchAutopilotRunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const int LOCK_TTL_SECONDS = 300;

    private const int RUN_ID_CACHE_TTL_SECONDS = 21600;

    public int $timeout = self::LOCK_TTL_SECONDS;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public ?string $runId = null;

    public function retryUntil(): Carbon
    {
        return now()->addHours(6);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly AutopilotTriggerType $triggerType,
        public readonly array $context,
        public readonly string $sourceId,
        public readonly ?string $correlationId = null,
    ) {
        $this->onQueue(config('ai.autopilot.queue_name', 'ai'));
    }

    /**
     * Execute the job.
     */
    public function handle(
        ChatAiActivityService $chatAiActivity,
        AutopilotRunSnapshotResolver $snapshotResolver,
        AutopilotRunStreamPublisher $streamPublisher,
        AiContextBuilderService $contextBuilder,
    ): void {
        $startedAt = microtime(true);
        $resolvedCorrelationId = $this->correlationId ?? (string) Str::orderedUuid();
        Log::withContext([
            'tenant_id' => $this->tenantId,
            'source_id' => $this->sourceId,
            'correlation_id' => $resolvedCorrelationId,
        ]);

        $ticketId = (string) ($this->context['ticket_id'] ?? '');
        $messageId = (string) ($this->context['message_id'] ?? '');
        $inputText = (string) ($this->context['body'] ?? '');
        $instanceId = (string) ($this->context['instance_id'] ?? '');

        // Idempotency: skip if another job already acquired the lock
        if (! $this->acquireMessageDispatchLock($messageId)) {
            $this->recordLockContentionMetric($messageId);
            Log::info('[DispatchAutopilotRunJob] Skipping: lock already acquired', [
                'tenant_id' => $this->tenantId,
                'message_id' => $messageId,
                'trigger_type' => $this->triggerType->value,
            ]);

            return;
        }

        ['trigger' => $trigger, 'agent' => $agent] = $this->resolveAgentAndTrigger();

        if (! $trigger && $agent instanceof AiAgent && $this->triggerType === AutopilotTriggerType::INBOUND_MESSAGE) {
            Log::info('[DispatchAutopilotRunJob] No active trigger found, using fallback active agent', [
                'tenant_id' => $this->tenantId,
                'agent_id' => (string) $agent->id,
            ]);
        }

        if (! $agent || ! $agent->is_active) {
            $rejectionReason = $this->resolveRejectionReason($instanceId);

            Log::info('[DispatchAutopilotRunJob] No trigger and no active fallback agent found', [
                'tenant_id' => $this->tenantId,
                'trigger_type' => $this->triggerType->value,
                'rejection_reason' => $rejectionReason,
            ]);

            $chatAiActivity->emitProcessingRejected(
                $this->tenantId,
                $ticketId,
                null,
                $rejectionReason,
                $messageId,
            );

            return;
        }

        $runId = (string) Str::orderedUuid();
        // Nota de domínio: em fluxos ad-hoc/simulator o playbook_id pode ficar nulo
        // (ver migration 2026-03-22). Neste dispatcher sempre resolvemos um playbook fallback.
        $playbookId = $this->resolvePlaybookId($agent);
        $run = $this->createRunRecord(
            $runId,
            $resolvedCorrelationId,
            $agent,
            $trigger,
            $ticketId,
            $messageId,
            $inputText,
            $instanceId
        );
        $this->runId = $runId;
        $this->persistRunIdForFailedHandler($runId);
        $contactId = $this->resolveContactId($ticketId);
        $modelId = is_string($agent->model_id) && $agent->model_id !== ''
            ? $agent->model_id
            : null;

        if ($modelId === null) {
            Log::warning('[DispatchAutopilotRunJob] Dispatching run without explicit model_id', [
                'run_id' => $runId,
                'tenant_id' => $this->tenantId,
                'agent_id' => (string) $agent->id,
                'trigger_type' => $this->triggerType->value,
            ]);
        }

        $agentFilePrompts = $this->resolveAgentFilePrompts($agent);

        $streamPayload = [
            'event' => 'ai.run.request',
            'run_id' => $runId,
            'tenant_id' => $this->tenantId,
            'agent_id' => (string) $agent->id,
            'trigger_id' => $trigger instanceof AiAgentTrigger ? (string) $trigger->id : null,
            'ticket_id' => $ticketId,
            'contact_id' => $contactId,
            'input_text' => $inputText,
            'source' => $trigger instanceof AiAgentTrigger ? 'autopilot_trigger' : 'fallback_agent',
            'trigger_type' => $this->triggerType->value,
            'source_id' => $this->sourceId,
            'correlation_id' => $resolvedCorrelationId,
            'instance_id' => $instanceId,
            'playbook_id' => $playbookId,
            'streaming_enabled' => true,
            'max_tokens' => (int) config('ai.autopilot.max_tokens', 800),
            'max_tool_iterations' => (int) config('ai.autopilot.max_tool_iterations', 5),
            'run_token_budget' => (int) config('ai.autopilot.run_token_budget', 3000),
            'compact_tool_results' => (bool) config('ai.autopilot.compact_tool_results', true) ? 'true' : 'false',
            'agent_system_prompt' => (string) ($agent->system_prompt ?? ''),
            'agent_file_prompts' => $agentFilePrompts,
            'requested_at' => now()->toIso8601String(),
        ];

        if ($modelId !== null) {
            $streamPayload['model'] = $modelId;
        }

        // QW1 + QW4: hidrata prompt/context/tools antes de publicar para evitar
        // 2–3 round-trips HTTP gateway→API por run. Falhas individuais são
        // toleradas: o gateway faz fallback HTTP normal quando o snapshot vier vazio.
        //
        // NOTA: agente sem tools configuradas (ai_agent_tools vazio) publica
        // payload SEM a chave 'tools'. O gateway detecta a ausência e opera
        // em modo conversacional puro (sem function calling). Isso NÃO é erro —
        // é comportamento intencional para agentes de atendimento geral.
        $snapshot = $snapshotResolver->resolve($this->tenantId, $agent, $ticketId);

        // Substitui o contexto mínimo do snapshot pelo contexto rico com conversation_history,
        // pois o AutopilotRunSnapshotResolver não inclui histórico de mensagens.
        try {
            $ticket = ChatTicket::query()
                ->where('tenant_id', $this->tenantId)
                ->find($ticketId);
            $currentMessage = $messageId !== ''
                ? ChatMessage::query()->find($messageId)
                : null;

            if ($ticket instanceof ChatTicket && $currentMessage instanceof ChatMessage) {
                $snapshot['context'] = $contextBuilder->build($ticket, $currentMessage);
            }
        } catch (\Throwable $e) {
            Log::warning('[DispatchAutopilotRunJob] Failed to build rich context, falling back to minimal snapshot', [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }

        $streamPayload['hydrated_at'] = $snapshot['hydrated_at'];

        if (is_string($snapshot['prompt']) && $snapshot['prompt'] !== '') {
            $streamPayload['prompt'] = $snapshot['prompt'];
        }

        if (is_array($snapshot['context'])) {
            $streamPayload['context'] = $snapshot['context'];
        }

        if (is_array($snapshot['tools']) && $snapshot['tools'] !== []) {
            $streamPayload['tools'] = $snapshot['tools'];
        }

        try {
            $streamPublisher->publish($streamPayload);
            $this->recordRunMetrics($startedAt, 'queued');

            $chatAiActivity->emitProcessingStarted(
                $this->tenantId,
                $ticketId,
                $runId,
                $messageId,
                ['source' => $streamPayload['source']]
            );
        } catch (\Throwable $exception) {
            $run->status = 'failed';
            $run->output = [
                'error' => $exception->getMessage(),
                'source' => 'autopilot_dispatcher',
            ];
            $run->completed_at = now();
            $run->save();
            $this->recordRunMetrics($startedAt, 'failed');

            $chatAiActivity->emitProcessingFailed(
                $this->tenantId,
                $ticketId,
                $runId,
                $exception->getMessage(),
                $messageId,
            );

            throw $exception;
        }

        $this->logTriggerExecution($trigger, $runId);
        $this->forgetPersistedRunIdForFailedHandler();

        Log::info('[DispatchAutopilotRunJob] Published ai.run.request', [
            'run_id' => $runId,
            'tenant_id' => $this->tenantId,
            'agent_id' => (string) $agent->id,
            'trigger_type' => $this->triggerType->value,
            'ticket_id' => $ticketId,
        ]);
    }

    /**
     * @return array{trigger: ?AiAgentTrigger, agent: ?AiAgent}
     */
    private function resolveAgentAndTrigger(): array
    {
        $ticketId = (string) ($this->context['ticket_id'] ?? '');

        // Sticky agent: se há um especialista ativo no ticket, usá-lo diretamente
        if ($this->triggerType === AutopilotTriggerType::INBOUND_MESSAGE && $ticketId !== '') {
            $stickyAgent = $this->resolveStickyAgent($ticketId);

            if ($stickyAgent instanceof AiAgent) {
                Log::info('[DispatchAutopilotRunJob] Sticky agent resolved', [
                    'tenant_id' => $this->tenantId,
                    'ticket_id' => $ticketId,
                    'agent_id' => (string) $stickyAgent->id,
                ]);

                return ['trigger' => null, 'agent' => $stickyAgent];
            }
        }

        $cacheKey = AiAgentTriggerObserver::cacheKey($this->tenantId);
        $triggers = Cache::remember($cacheKey, 3600, function () {
            return AiAgentTrigger::query()
                ->where('tenant_id', $this->tenantId)
                ->where('status', 'active')
                ->with([
                    'agent' => fn ($query) => $query
                        ->where('is_active', true)
                        ->with(['files' => fn ($filesQuery) => $filesQuery->orderBy('slug')]),
                ])
                ->get();
        });

        if ($triggers instanceof \Illuminate\Support\Collection) {
            $trigger = $triggers->first(function (AiAgentTrigger $candidate) {
                return (string) $candidate->type === $this->triggerType->value
                    && $candidate->agent instanceof AiAgent
                    && (bool) $candidate->agent->is_active;
            });

            if ($trigger instanceof AiAgentTrigger && $trigger->agent instanceof AiAgent) {
                return ['trigger' => $trigger, 'agent' => $trigger->agent];
            }
        }

        if ($this->triggerType !== AutopilotTriggerType::INBOUND_MESSAGE) {
            return ['trigger' => null, 'agent' => null];
        }

        $fallbackAgentCacheKey = sprintf('autopilot:fallback-agent:tenant:%s', $this->tenantId);

        return Cache::remember($fallbackAgentCacheKey, 3600, function () {
            $agent = AiAgent::query()
                ->with(['files' => fn ($filesQuery) => $filesQuery->orderBy('slug')])
                ->where('tenant_id', $this->tenantId)
                ->where('is_active', true)
                ->where('type', 'general')
                ->first();

            return ['trigger' => null, 'agent' => $agent instanceof AiAgent ? $agent : null];
        });
    }

    private function resolveStickyAgent(string $ticketId): ?AiAgent
    {
        $stickyId = ChatTicket::query()
            ->where('id', $ticketId)
            ->where('tenant_id', $this->tenantId)
            ->value('current_ai_agent_id');

        if (! is_string($stickyId) || $stickyId === '') {
            return null;
        }

        return AiAgent::query()
            ->with(['files' => fn ($filesQuery) => $filesQuery->orderBy('slug')])
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->find($stickyId);
    }

    private function resolvePlaybookId(AiAgent $agent): string
    {
        $cacheKey = sprintf(
            'autopilot:playbook:tenant:%s:agent:%s',
            $this->tenantId,
            (string) $agent->id,
        );

        return Cache::remember($cacheKey, 3600, function () {
            $playbook = AiAutopilotPlaybook::query()->firstOrCreate(
                [
                    'tenant_id' => $this->tenantId,
                    'name' => 'Inbound Chat Autopilot',
                ],
                [
                    'description' => 'Fallback playbook used to track inbound chat AI runs.',
                    'trigger_type' => $this->triggerType->value,
                    'version' => 1,
                    'steps' => [
                        ['step' => 1, 'name' => 'Dispatch inbound AI run'],
                    ],
                    'metadata' => ['source' => 'chat_inbound_fallback'],
                    'is_active' => true,
                ]
            );

            return (string) $playbook->id;
        });
    }

    private function resolveRejectionReason(string $instanceId): string
    {
        if ($this->triggerType === AutopilotTriggerType::INBOUND_MESSAGE) {
            $integrationFallbackMessage = $this->resolveIntegrationFallbackMessage($instanceId);
            if ($integrationFallbackMessage !== null) {
                return $integrationFallbackMessage;
            }

            $agentFallbackMessage = $this->resolveAgentFallbackMessage();
            if ($agentFallbackMessage !== null) {
                return $agentFallbackMessage;
            }

            return 'No momento nao consegui concluir seu atendimento automaticamente. Vou te conectar agora a um especialista comercial para dar continuidade.';
        }

        return 'No active AI agent available for the trigger.';
    }

    private function resolveIntegrationFallbackMessage(string $instanceId): ?string
    {
        if ($instanceId === '') {
            return null;
        }

        $instance = ChatInstance::query()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $instanceId)
            ->first(['settings_json']);

        if (! $instance instanceof ChatInstance) {
            return null;
        }

        $settings = is_array($instance->settings_json) ? $instance->settings_json : [];
        $fallbackMessage = $settings['integration_fallback_message'] ?? null;

        if (! is_string($fallbackMessage)) {
            return null;
        }

        $normalized = trim($fallbackMessage);

        return $normalized === '' ? null : $normalized;
    }

    private function resolveAgentFallbackMessage(): ?string
    {
        $fallbackMessage = AiAgent::query()
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->whereNotNull('fallback_message')
            ->where('fallback_message', '!=', '')
            ->orderByRaw("CASE WHEN type = 'general' THEN 0 WHEN type = 'qualifier' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->value('fallback_message');

        if (! is_string($fallbackMessage)) {
            return null;
        }

        $normalized = trim($fallbackMessage);

        return $normalized === '' ? null : $normalized;
    }

    private function resolveContactId(string $ticketId): string
    {
        if ($ticketId === '') {
            return '';
        }

        $contactId = ChatTicket::query()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $ticketId)
            ->value('contact_id');

        return (string) ($contactId ?? '');
    }

    private function createRunRecord(
        string $runId,
        string $correlationId,
        AiAgent $agent,
        ?AiAgentTrigger $trigger,
        string $ticketId,
        string $messageId,
        string $inputText,
        string $instanceId,
    ): AiAutopilotRun {
        return AiAutopilotRun::query()->create([
            'id' => $runId,
            'tenant_id' => $this->tenantId,
            'playbook_id' => $this->resolvePlaybookId($agent),
            'status' => 'queued',
            'correlation_id' => $correlationId,
            'playbook_version' => 1,
            'streaming_enabled' => true,
            'input_context' => [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'body' => $inputText,
                'instance_id' => $instanceId,
                'source_id' => $this->sourceId,
                'source_type' => (string) ($this->context['source_type'] ?? 'ticket'),
                'agent_id' => (string) $agent->id,
                'agent_type' => (string) $agent->type,
                // agent_role é APENAS observabilidade/telemetria — NÃO é usado para
                // autorização. As tools são resolvidas via ai_agent_tools (banco).
                'agent_role' => (string) (data_get($agent->metadata, 'role') ?? $agent->getAttribute('role') ?? ''),
                'trigger_id' => $trigger instanceof AiAgentTrigger ? (string) $trigger->id : null,
                'trigger_type' => $this->triggerType->value,
                'dispatch_source' => $trigger instanceof AiAgentTrigger ? 'autopilot_trigger' : 'fallback_agent',
                'correlation_id' => $correlationId,
            ],
            'started_at' => now(),
        ]);
    }

    private function logTriggerExecution(?AiAgentTrigger $trigger, string $runId): void
    {
        if ($trigger instanceof AiAgentTrigger) {
            $trigger->last_run_at = now();
            $trigger->save();
        }
    }

    private function acquireMessageDispatchLock(string $messageId): bool
    {
        if ($messageId === '') {
            return true;
        }

        $lockKey = sprintf('autopilot:lock:tenant:%s:msg:%s', $this->tenantId, $messageId);

        /** @var \Illuminate\Redis\Connections\Connection $redis */
        $redis = Redis::connection(config('gateway.redis.connection', 'gateway'));
        $acquired = $redis instanceof PredisConnection
            ? $redis->client()->executeRaw(['SET', $lockKey, '1', 'EX', '300', 'NX'])
            : $redis->set($lockKey, '1', ['EX' => self::LOCK_TTL_SECONDS, 'NX']);

        return $acquired === true || $acquired === 'OK';
    }

    public function failed(Throwable $exception): void
    {
        $resolvedCorrelationId = $this->correlationId ?? $this->sourceId;
        Log::withContext([
            'tenant_id' => $this->tenantId,
            'source_id' => $this->sourceId,
            'correlation_id' => $resolvedCorrelationId,
        ]);

        if (! is_string($this->runId) || $this->runId === '') {
            $this->runId = $this->resolvePersistedRunIdForFailedHandler();
        }

        if (! is_string($this->runId) || $this->runId === '') {
            Log::error('[DispatchAutopilotRunJob] Job failed without persisted run id', [
                'tenant_id' => $this->tenantId,
                'source_id' => $this->sourceId,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return;
        }

        $run = AiAutopilotRun::query()
            ->where('tenant_id', $this->tenantId)
            ->find($this->runId);

        if (! $run instanceof AiAutopilotRun) {
            Log::error('[DispatchAutopilotRunJob] Job failed but run was not found', [
                'run_id' => $this->runId,
                'tenant_id' => $this->tenantId,
                'source_id' => $this->sourceId,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return;
        }

        $output = is_array($run->output) ? $run->output : [];
        $output['error'] = $exception->getMessage();
        $output['error_code'] = 'dispatch_job_failed';
        $output['exception_class'] = $exception::class;

        $run->status = 'failed';
        $run->output = $output;
        $run->completed_at = now();
        $run->save();
        $this->recordRunMetrics(
            $run->started_at !== null ? (float) $run->started_at->getTimestamp() : microtime(true),
            'failed'
        );

        $inputContext = is_array($run->input_context) ? $run->input_context : [];
        $correlationId = (string) ($inputContext['correlation_id'] ?? $this->correlationId ?? $this->sourceId);
        $ticketId = (string) ($inputContext['ticket_id'] ?? '');

        Log::error('[DispatchAutopilotRunJob] Job failed after retries exhausted', [
            'run_id' => (string) $run->id,
            'tenant_id' => (string) $run->tenant_id,
            'correlation_id' => $correlationId,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
        ]);

        AiRunFailed::dispatch(
            (string) $run->tenant_id,
            $ticketId,
            (string) $run->id,
            $correlationId,
            $exception->getMessage(),
            'dispatch_job_failed',
        );
        $this->forgetPersistedRunIdForFailedHandler();
    }

    private function recordRunMetrics(float $startedAt, string $status): void
    {
        try {
            /** @var MetricsService $metrics */
            $metrics = app(MetricsService::class);
            $durationSeconds = max(0.0, microtime(true) - $startedAt);

            $metrics->recordAutopilotRunDuration($durationSeconds, [
                'tenant_id' => $this->tenantId,
                'trigger_type' => $this->triggerType->value,
                'status' => $status,
            ]);
            $metrics->recordAutopilotToolIterations(0, [
                'tenant_id' => $this->tenantId,
                'trigger_type' => $this->triggerType->value,
                'status' => $status,
            ]);
        } catch (\Throwable) {
            // Best effort metrics only.
        }
    }

    private function recordLockContentionMetric(string $messageId): void
    {
        try {
            /** @var MetricsService $metrics */
            $metrics = app(MetricsService::class);
            $metrics->recordAutopilotLockContention(1, [
                'tenant_id' => $this->tenantId,
                'trigger_type' => $this->triggerType->value,
                'has_message_id' => $messageId !== '' ? 'true' : 'false',
            ]);
        } catch (\Throwable) {
            // Best effort metrics only.
        }
    }

    private function persistRunIdForFailedHandler(string $runId): void
    {
        Cache::put(
            $this->runIdCacheKeyForFailedHandler(),
            $runId,
            now()->addSeconds(self::RUN_ID_CACHE_TTL_SECONDS)
        );
    }

    private function resolvePersistedRunIdForFailedHandler(): ?string
    {
        $resolved = Cache::get($this->runIdCacheKeyForFailedHandler());

        return is_string($resolved) && $resolved !== '' ? $resolved : null;
    }

    private function forgetPersistedRunIdForFailedHandler(): void
    {
        Cache::forget($this->runIdCacheKeyForFailedHandler());
    }

    private function runIdCacheKeyForFailedHandler(): string
    {
        $messageId = (string) ($this->context['message_id'] ?? '');
        $sourceDiscriminator = $messageId !== '' ? $messageId : $this->sourceId;

        return sprintf(
            'autopilot:dispatch:run-id:tenant:%s:source:%s',
            $this->tenantId,
            $sourceDiscriminator
        );
    }

    /**
     * Carrega arquivos do agente (IDENTITY.md, SOUL.md, etc.) e formata
     * cada um como `[slug]\ncontent`, igual ao InternalAiController de delegação.
     *
     * @return list<string>
     */
    private function resolveAgentFilePrompts(AiAgent $agent): array
    {
        $files = $agent->relationLoaded('files')
            ? $agent->getRelation('files')
            : AiAgentFile::query()
                ->where('tenant_id', (string) $agent->tenant_id)
                ->where('agent_id', (string) $agent->id)
                ->orderBy('slug')
                ->get(['slug', 'content']);

        return collect($files)
            ->filter(static fn (AiAgentFile $file): bool => trim((string) $file->content) !== '')
            ->map(static fn (AiAgentFile $file): string => sprintf('[%s]'."\n".'%s', (string) $file->slug, (string) $file->content))
            ->values()
            ->all();
    }
}
