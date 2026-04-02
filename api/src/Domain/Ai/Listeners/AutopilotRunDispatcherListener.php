<?php

declare(strict_types=1);

namespace Domain\Ai\Listeners;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentTrigger;
use Domain\Ai\Models\AiAutopilotPlaybook;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Models\AiAutopilotTriggerLog;
use Domain\Ai\Observers\AiAgentTriggerObserver;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatAiActivityService;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Listens for AutopilotTriggerFired events and dispatches AI run requests
 * to the Gateway via Redis Streams.
 *
 * This listener resolves the appropriate agent for the given tenant and trigger type,
 * then publishes an ai.run.request message to the Redis Stream so the Gateway
 * AiRunRequestConsumer can process it.
 */
final readonly class AutopilotRunDispatcherListener
{
    public function __construct(
        private ChatAiActivityService $chatAiActivity,
    ) {}

    /**
     * Handle the AutopilotTriggerFired event.
     */
    public function handle(AutopilotTriggerFired $event): void
    {
        $ticketId = (string) ($event->context['ticket_id'] ?? '');
        $messageId = (string) ($event->context['message_id'] ?? '');
        $inputText = (string) ($event->context['body'] ?? '');
        $instanceId = (string) ($event->context['instance_id'] ?? '');

        if (! $this->acquireMessageDispatchLock($event->tenantId, $messageId)) {
            Log::info('[AutopilotRunDispatcher] Skipping: lock already acquired for message_id', [
                'tenant_id' => $event->tenantId,
                'message_id' => $messageId,
                'trigger_type' => $event->triggerType->value,
            ]);

            return;
        }

        ['trigger' => $trigger, 'agent' => $agent] = $this->resolveAgentAndTrigger($event->tenantId, $event->triggerType);

        if (! $trigger && $agent instanceof \Domain\Ai\Models\AiAgent && $event->triggerType === AutopilotTriggerType::INBOUND_MESSAGE) {
            Log::info('[AutopilotRunDispatcher] No active trigger found, using fallback active agent', [
                'tenant_id' => $event->tenantId,
                'agent_id' => (string) $agent->id,
            ]);
        }

        if (! $agent || ! $agent->is_active) {
            $rejectionReason = $this->resolveRejectionReason($event->tenantId, $event->triggerType, $trigger, $instanceId);

            Log::info('[AutopilotRunDispatcher] No trigger and no active fallback agent found', [
                'tenant_id' => $event->tenantId,
                'trigger_type' => $event->triggerType->value,
                'rejection_reason' => $rejectionReason,
            ]);

            $this->chatAiActivity->emitProcessingRejected(
                $event->tenantId,
                $ticketId,
                null,
                $rejectionReason,
                $messageId,
            );

            return;
        }

        $runId = (string) Str::orderedUuid();
        $playbookId = $this->resolvePlaybookId($event, $agent);
        $run = $this->createRunRecord($runId, $event, $agent, $trigger, $ticketId, $messageId, $inputText, $instanceId);
        $contactId = $this->resolveContactId($event->tenantId, $ticketId);
        $modelId = is_string($agent->model_id) && $agent->model_id !== ''
            ? $agent->model_id
            : null;

        if ($modelId === null) {
            Log::warning('[AutopilotRunDispatcher] Dispatching run without explicit model_id', [
                'run_id' => $runId,
                'tenant_id' => $event->tenantId,
                'agent_id' => (string) $agent->id,
                'trigger_type' => $event->triggerType->value,
            ]);
        }

        // Minimal payload (~2KB) — Gateway fetches heavy data via Internal API:
        // - Prompts: GET /internal/ai/prompt/{tenantId}
        // - Agent files: GET /internal/ai/tools/{agentId}
        // - Context/messages: GET /internal/ai/context/{ticketId}
        $streamPayload = [
            'event' => 'ai.run.request',
            'run_id' => $runId,
            'tenant_id' => $event->tenantId,
            'agent_id' => (string) $agent->id,
            'trigger_id' => $trigger instanceof \Domain\Ai\Models\AiAgentTrigger ? (string) $trigger->id : null,
            'ticket_id' => $ticketId,
            'contact_id' => $contactId,
            'input_text' => $inputText,
            'source' => $trigger instanceof \Domain\Ai\Models\AiAgentTrigger ? 'autopilot_trigger' : 'fallback_agent',
            'trigger_type' => $event->triggerType->value,
            'source_id' => $event->sourceId,
            'instance_id' => $instanceId,
            'playbook_id' => $playbookId,
            'streaming_enabled' => true,
            'max_tokens' => (int) config('ai.autopilot.max_tokens', 800),
            'max_tool_iterations' => (int) config('ai.autopilot.max_tool_iterations', 5),
            'run_token_budget' => (int) config('ai.autopilot.run_token_budget', 3000),
            'compact_tool_results' => (bool) config('ai.autopilot.compact_tool_results', true) ? 'true' : 'false',
            'requested_at' => now()->toIso8601String(),
        ];

        if ($modelId !== null) {
            $streamPayload['model'] = $modelId;
        }

        try {
            /** @var \Illuminate\Redis\Connections\Connection $redis */
            $redis = Redis::connection(config('gateway.redis.connection', 'gateway'));
            $this->publishRunRequestToStream($redis, $streamPayload);

            $this->chatAiActivity->emitProcessingStarted(
                $event->tenantId,
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

            $this->chatAiActivity->emitProcessingFailed(
                $event->tenantId,
                $ticketId,
                $runId,
                $exception->getMessage(),
                $messageId,
            );

            throw $exception;
        }

        // Log the trigger execution
        $this->logTriggerExecution($trigger, $runId, $event);

        Log::info('[AutopilotRunDispatcher] Published ai.run.request', [
            'run_id' => $runId,
            'tenant_id' => $event->tenantId,
            'agent_id' => (string) $agent->id,
            'trigger_type' => $event->triggerType->value,
            'ticket_id' => $ticketId,
        ]);
    }

    private function createRunRecord(
        string $runId,
        AutopilotTriggerFired $event,
        AiAgent $agent,
        ?AiAgentTrigger $trigger,
        string $ticketId,
        string $messageId,
        string $inputText,
        string $instanceId,
    ): AiAutopilotRun {
        return AiAutopilotRun::query()->create([
            'id' => $runId,
            'tenant_id' => $event->tenantId,
            'playbook_id' => $this->resolvePlaybookId($event, $agent),
            'status' => 'queued',
            'playbook_version' => 1,
            'streaming_enabled' => true,
            'input_context' => [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'body' => $inputText,
                'instance_id' => $instanceId,
                'source_id' => $event->sourceId,
                'source_type' => (string) ($event->context['source_type'] ?? 'ticket'),
                'agent_id' => (string) $agent->id,
                'agent_type' => (string) $agent->type,
                'agent_role' => (string) $agent->getAttribute('role'),
                'trigger_id' => $trigger instanceof \Domain\Ai\Models\AiAgentTrigger ? (string) $trigger->id : null,
                'trigger_type' => $event->triggerType->value,
                'dispatch_source' => $trigger instanceof \Domain\Ai\Models\AiAgentTrigger ? 'autopilot_trigger' : 'fallback_agent',
            ],
            'started_at' => now(),
        ]);
    }

    private function resolvePlaybookId(AutopilotTriggerFired $event, AiAgent $agent): string
    {
        $cacheKey = sprintf(
            'autopilot:playbook:tenant:%s:agent:%s',
            $event->tenantId,
            (string) $agent->id,
        );

        return Cache::remember($cacheKey, 3600, function () use ($event): string {
            $playbook = AiAutopilotPlaybook::query()->firstOrCreate(
                [
                    'tenant_id' => $event->tenantId,
                    'name' => 'Inbound Chat Autopilot',
                ],
                [
                    'description' => 'Fallback playbook used to track inbound chat AI runs.',
                    'trigger_type' => $event->triggerType->value,
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

    /**
     * @return array{trigger: ?AiAgentTrigger, agent: ?AiAgent}
     */
    private function resolveAgentAndTrigger(string $tenantId, AutopilotTriggerType $triggerType): array
    {
        $cacheKey = AiAgentTriggerObserver::cacheKey($tenantId);
        $triggers = Cache::remember($cacheKey, 3600, function () use ($tenantId) {
            return AiAgentTrigger::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->with([
                    'agent' => fn ($query) => $query
                        ->where('is_active', true)
                        ->with(['files' => fn ($filesQuery) => $filesQuery->orderBy('slug')]),
                ])
                ->get();
        });

        if ($triggers instanceof \Illuminate\Support\Collection) {
            $trigger = $triggers->first(function (AiAgentTrigger $candidate) use ($triggerType): bool {
                return (string) $candidate->type === $triggerType->value
                    && $candidate->agent instanceof AiAgent
                    && (bool) $candidate->agent->is_active;
            });

            if ($trigger instanceof AiAgentTrigger && $trigger->agent instanceof AiAgent) {
                return ['trigger' => $trigger, 'agent' => $trigger->agent];
            }
        }

        if ($triggerType !== AutopilotTriggerType::INBOUND_MESSAGE) {
            return ['trigger' => null, 'agent' => null];
        }

        $fallbackAgentCacheKey = sprintf('autopilot:fallback-agent:tenant:%s', $tenantId);
        $fallbackAgent = Cache::remember($fallbackAgentCacheKey, 3600, function () use ($tenantId): ?AiAgent {
            return AiAgent::query()
                ->with(['files' => fn ($filesQuery) => $filesQuery->orderBy('slug')])
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('type', 'general')
                ->first();
        });

        return ['trigger' => null, 'agent' => $fallbackAgent instanceof AiAgent ? $fallbackAgent : null];
    }

    private function resolveRejectionReason(string $tenantId, AutopilotTriggerType $triggerType, ?AiAgentTrigger $trigger, string $instanceId): string
    {
        if ($triggerType === AutopilotTriggerType::INBOUND_MESSAGE && ! $trigger instanceof \Domain\Ai\Models\AiAgentTrigger) {
            return $this->resolveInboundFallbackMessage($tenantId, $instanceId);
        }

        return 'No active AI agent available for the inbound message.';
    }

    private function resolveInboundFallbackMessage(string $tenantId, string $instanceId): string
    {
        $integrationFallbackMessage = $this->resolveIntegrationFallbackMessage($tenantId, $instanceId);
        if ($integrationFallbackMessage !== null) {
            return $integrationFallbackMessage;
        }

        $agentFallbackMessage = $this->resolveAgentFallbackMessage($tenantId);
        if ($agentFallbackMessage !== null) {
            return $agentFallbackMessage;
        }

        return 'No momento nao consegui concluir seu atendimento automaticamente. Vou te conectar agora a um especialista comercial para dar continuidade.';
    }

    private function resolveIntegrationFallbackMessage(string $tenantId, string $instanceId): ?string
    {
        if ($instanceId === '') {
            return null;
        }

        $instance = ChatInstance::query()
            ->where('tenant_id', $tenantId)
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

    private function resolveAgentFallbackMessage(string $tenantId): ?string
    {
        $fallbackMessage = AiAgent::query()
            ->where('tenant_id', $tenantId)
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

    /**
     * Resolves the contact_id for the given ticket.
     */
    private function resolveContactId(string $tenantId, string $ticketId): string
    {
        if ($ticketId === '') {
            return '';
        }

        $contactId = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $ticketId)
            ->value('contact_id');

        return (string) ($contactId ?? '');
    }

    /**
     * Log the trigger execution for audit purposes.
     */
    private function logTriggerExecution(?AiAgentTrigger $trigger, string $runId, AutopilotTriggerFired $event): void
    {
        if ($trigger instanceof \Domain\Ai\Models\AiAgentTrigger) {
            $trigger->last_run_at = now();
            $trigger->save();
        }

        if ($trigger && class_exists(AiAutopilotTriggerLog::class)) {
            try {
                \Domain\Ai\Models\AiAutopilotTriggerLog::query()->create([
                    'tenant_id' => $event->tenantId,
                    'trigger_id' => (string) $trigger->id,
                    'run_id' => $runId,
                    'trigger_type' => $event->triggerType->value,
                    'source_id' => $event->sourceId,
                    'source_type' => (string) ($event->context['source_type'] ?? 'ticket'),
                    'playbook_id' => $trigger->getAttribute('playbook_id'),
                    'context' => $event->context,
                    'status' => 'dispatched',
                ]);
            } catch (\Throwable $e) {
                Log::warning('[AutopilotRunDispatcher] Failed to log trigger execution', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function acquireMessageDispatchLock(string $tenantId, string $messageId): bool
    {
        if ($messageId === '') {
            return true;
        }

        $lockKey = sprintf('autopilot:lock:tenant:%s:msg:%s', $tenantId, $messageId);
        /** @var \Illuminate\Redis\Connections\Connection $redis */
        $redis = Redis::connection(config('gateway.redis.connection', 'gateway'));
        $acquired = $redis instanceof PredisConnection
            ? $redis->client()->executeRaw(['SET', $lockKey, '1', 'EX', '60', 'NX'])
            : $redis->set($lockKey, '1', ['EX' => 60, 'NX']);

        return $acquired === true || $acquired === 'OK';
    }

    /**
     * @param  \Illuminate\Redis\Connections\Connection  $redis
     * @param  array<string, mixed>  $streamPayload
     */
    private function publishRunRequestToStream($redis, array $streamPayload): void
    {
        $fields = $this->normalizeStreamFields($streamPayload);

        if ($redis instanceof PredisConnection) {
            $arguments = ['XADD', 'ai.run.request', '*'];

            foreach ($fields as $key => $value) {
                $arguments[] = (string) $key;
                $arguments[] = $value;
            }

            $redis->client()->executeRaw($arguments);

            return;
        }

        $redis->xadd('ai.run.request', '*', $fields);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    private function normalizeStreamFields(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $key => $value) {
            if (is_string($value)) {
                $normalized[$key] = $value;

                continue;
            }

            if (is_int($value) || is_float($value)) {
                $normalized[$key] = (string) $value;

                continue;
            }

            if (is_bool($value)) {
                $normalized[$key] = $value ? '1' : '0';

                continue;
            }

            if ($value === null) {
                $normalized[$key] = '';

                continue;
            }

            $normalized[$key] = json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $normalized;
    }
}
