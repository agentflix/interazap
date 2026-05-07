<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Performance seed for AI context.
 *
 * Seeds all 17 AI tables per tenant using raw inserts.
 * Total: ~25,750 records across 50 tenants.
 */
final class PerformanceAiSeeder
{
    use WithoutModelEvents;

    private const int BATCH_SIZE = 1000;

    public function seedForTenant(string $tenantId): void
    {
        $userIds = DB::table('auth_users')->where('tenant_id', $tenantId)->pluck('id')->toArray();
        $negotiationIds = DB::table('crm_negotiations')->where('tenant_id', $tenantId)->pluck('id')->toArray();
        $ticketIds = DB::table('chat_tickets')->where('tenant_id', $tenantId)->pluck('id')->toArray();

        $agentIds = $this->seedAgents($tenantId);
        $this->seedAgentSkills($tenantId, $agentIds);
        $this->seedAgentDelegations($tenantId, $agentIds);
        $this->seedAgentFiles($tenantId, $agentIds, $userIds);

        $toolIds = $this->seedAutopilotTools($tenantId);
        $this->seedAgentTools($tenantId, $agentIds, $toolIds);

        $playbookIds = $this->seedAutopilotPlaybooks($tenantId);
        $this->seedAutopilotGuardrails($tenantId);
        $this->seedAgentTriggers($tenantId, $agentIds);

        $runIds = $this->seedAutopilotRuns($tenantId, $playbookIds);
        $this->seedAutopilotActions($tenantId, $runIds);
        $this->seedAutopilotApprovals($tenantId, $runIds, $userIds);

        $this->seedUsageLogs($tenantId, $userIds);
        $this->seedSellerNotifications($tenantId, $userIds, $negotiationIds);
        $this->seedPostSaleSchedules($tenantId, $negotiationIds, $ticketIds);
        $this->seedConversationSummaries($tenantId, $ticketIds);
        $this->seedAgentChannels($tenantId, $agentIds);
    }

    /** @return array<int, string> */
    private function seedAgents(string $tenantId): array
    {
        $types = ['assistant' => 40, 'classifier' => 30, 'general' => 30];
        $models = ['gpt-4o-mini', 'gpt-4o', 'claude-3-5-sonnet', 'gemini-2.0-flash'];
        $agents = [];
        $ids = [];
        $count = random_int(3, 7);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $hasVoice = random_int(0, 100) > 80;

            $agents[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'name' => 'Agente '.($i + 1),
                'type' => PerformanceSeeder::weightedRandom($types),
                'model_id' => $models[array_rand($models)],
                'system_prompt' => 'Voce e um assistente util.',
                'max_tokens' => random_int(512, 4096),
                'temperature' => random_int(0, 20) / 10,
                'top_p' => random_int(5, 10) / 10,
                'is_active' => random_int(0, 100) > 10,
                'parent_agent_id' => $i > 0 && (bool) random_int(0, 1) ? $ids[array_rand($ids)] : null,
                'classifier_model' => $models[array_rand($models)],
                'token_budget_input' => random_int(0, 100000),
                'token_budget_output' => random_int(0, 50000),
                'fallback_message' => 'Desculpe, nao entendi. Pode reformular?',
                'metadata' => json_encode(['version' => '1.0']),
                'voice_response_mode' => $hasVoice ? 'voice' : 'text',
                'stt_model' => $hasVoice ? 'whisper-1' : null,
                'stt_language' => $hasVoice ? 'pt' : null,
                'tts_model' => $hasVoice ? 'tts-1' : null,
                'tts_voice' => $hasVoice ? 'alloy' : null,
                'tts_speed' => $hasVoice ? random_int(8, 15) / 10 : 1,
                'description' => 'Descricao do agente '.($i + 1),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('ai_agents', $agents, self::BATCH_SIZE);

        return $ids;
    }

    private function seedAgentSkills(string $tenantId, array $agentIds): void
    {
        $skills = ['vendas', 'suporte_tecnico', 'financeiro', 'onboarding', 'retencao', 'classificacao', 'proposta'];
        $agentSkills = [];

        foreach ($agentIds as $agentId) {
            $skillCount = random_int(1, 3);
            for ($s = 0; $s < $skillCount; $s++) {
                $agentSkills[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tenant_id' => $tenantId,
                    'agent_id' => $agentId,
                    'name' => $skills[$s % count($skills)],
                    'description' => 'Skill de '.$skills[$s % count($skills)],
                    'is_active' => random_int(0, 100) > 10,
                    'metadata' => json_encode(['level' => random_int(1, 5)]),
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        PerformanceSeeder::insertBatch('ai_agent_skills', $agentSkills, self::BATCH_SIZE);
    }

    private function seedAgentDelegations(string $tenantId, array $agentIds): void
    {
        $delegations = [];
        $count = min(count($agentIds) - 1, 3);

        for ($i = 0; $i < $count; $i++) {
            $source = $agentIds[$i];
            $target = $agentIds[($i + 1) % count($agentIds)];

            $delegations[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'source_agent_id' => $source,
                'target_agent_id' => $target,
                'max_depth' => random_int(1, 3),
                'is_active' => random_int(0, 100) > 10,
                'metadata' => json_encode(['condition' => 'fallback']),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        if (! empty($delegations)) {
            PerformanceSeeder::insertBatch('ai_agent_delegations', $delegations, self::BATCH_SIZE);
        }
    }

    private function seedAgentFiles(string $tenantId, array $agentIds, array $userIds): void
    {
        $files = [];

        foreach ($agentIds as $agentId) {
            $fileCount = random_int(1, 3);
            for ($i = 0; $i < $fileCount; $i++) {
                $files[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tenant_id' => $tenantId,
                    'agent_id' => $agentId,
                    'slug' => 'doc_'.PerformanceSeeder::uuid(),
                    'content' => fake('pt_BR')->paragraph(),
                    'updated_by' => ! empty($userIds) ? $userIds[array_rand($userIds)] : null,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        PerformanceSeeder::insertBatch('ai_agent_files', $files, self::BATCH_SIZE);
    }

    /** @return array<int, string> */
    private function seedAutopilotTools(string $tenantId): array
    {
        $tools = [];
        $ids = [];
        $count = random_int(8, 12);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $isSystem = $i < 3;

            $tools[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'name' => 'tool_'.Str::random(8),
                'display_name' => 'Ferramenta '.($i + 1),
                'description' => 'Descricao da ferramenta '.($i + 1),
                'handler_class' => 'App\\Tools\\Tool'.random_int(1, 100),
                'parameters_schema' => json_encode(['type' => 'object']),
                'is_system' => $isSystem,
                'is_active' => random_int(0, 100) > 10,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('ai_autopilot_tools', $tools, self::BATCH_SIZE);

        return $ids;
    }

    private function seedAgentTools(string $tenantId, array $agentIds, array $toolIds): void
    {
        $links = [];
        $usedPairs = [];
        $count = min(count($agentIds) * 3, 15);

        for ($i = 0; $i < $count; $i++) {
            $agentId = $agentIds[array_rand($agentIds)];
            $toolId = $toolIds[array_rand($toolIds)];
            $pair = "{$agentId}:{$toolId}";

            if (isset($usedPairs[$pair])) {
                continue;
            }
            $usedPairs[$pair] = true;

            $links[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'agent_id' => $agentId,
                'tool_id' => $toolId,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        if (! empty($links)) {
            PerformanceSeeder::insertBatch('ai_agent_tools', $links, self::BATCH_SIZE);
        }
    }

    /** @return array<int, string> */
    private function seedAutopilotPlaybooks(string $tenantId): array
    {
        $triggerTypes = ['manual' => 30, 'auto' => 50, 'scheduled' => 20];
        $playbooks = [];
        $ids = [];
        $count = random_int(3, 7);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;

            $playbooks[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'name' => 'Playbook '.($i + 1),
                'description' => 'Descricao do playbook '.($i + 1),
                'trigger_type' => PerformanceSeeder::weightedRandom($triggerTypes),
                'version' => random_int(1, 5),
                'steps' => json_encode(['step1' => 'action']),
                'metadata' => json_encode(['author' => 'system']),
                'is_active' => random_int(0, 100) > 10,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('ai_autopilot_playbooks', $playbooks, self::BATCH_SIZE);

        return $ids;
    }

    private function seedAutopilotGuardrails(string $tenantId): void
    {
        $ruleTypes = ['block' => 30, 'allow' => 50, 'warn' => 20];
        $guardrails = [];
        $count = random_int(3, 7);

        for ($i = 0; $i < $count; $i++) {
            $guardrails[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Guardrail '.($i + 1),
                'description' => 'Regra de guardrail '.($i + 1),
                'rule_type' => PerformanceSeeder::weightedRandom($ruleTypes),
                'conditions' => json_encode(['keyword' => 'bloqueado']),
                'priority' => $i,
                'is_active' => random_int(0, 100) > 10,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('ai_autopilot_guardrails', $guardrails, self::BATCH_SIZE);
    }

    private function seedAgentTriggers(string $tenantId, array $agentIds): void
    {
        $types = ['keyword' => 50, 'schedule' => 30, 'webhook' => 20];
        $triggers = [];

        foreach ($agentIds as $agentId) {
            $triggerCount = random_int(1, 2);
            for ($t = 0; $t < $triggerCount; $t++) {
                $triggers[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tenant_id' => $tenantId,
                    'agent_id' => $agentId,
                    'type' => PerformanceSeeder::weightedRandom($types),
                    'config' => json_encode(['pattern' => 'trigger_'.random_int(1, 100)]),
                    'status' => random_int(0, 100) > 10 ? 'active' : 'inactive',
                    'last_run_at' => PerformanceSeeder::randomDate(),
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        PerformanceSeeder::insertBatch('ai_agent_triggers', $triggers, self::BATCH_SIZE);
    }

    /** @return array<int, string> */
    private function seedAutopilotRuns(string $tenantId, array $playbookIds): array
    {
        $statusWeights = ['running' => 10, 'completed' => 60, 'failed' => 20, 'cancelled' => 10];
        $classifiers = ['message_received', 'scheduled', 'keyword', 'webhook', 'ticket_created', 'ticket_closed', null];
        $runs = [];
        $ids = [];
        $count = random_int(20, 40);

        for ($i = 0; $i < $count; $i++) {
            $id = PerformanceSeeder::uuid();
            $ids[] = $id;
            $status = PerformanceSeeder::weightedRandom($statusWeights);
            $startedAt = PerformanceSeeder::randomDate();
            $hasClassifier = (bool) random_int(0, 1);

            $runs[] = [
                'id' => $id,
                'tenant_id' => $tenantId,
                'playbook_id' => ! empty($playbookIds) ? $playbookIds[array_rand($playbookIds)] : null,
                'status' => $status,
                'playbook_version' => random_int(1, 5),
                'input_context' => json_encode(['ticket_id' => PerformanceSeeder::uuid()]),
                'output' => $status === 'completed' ? json_encode(['result' => 'success']) : null,
                'started_at' => $startedAt,
                'completed_at' => $status === 'completed' ? $startedAt->copy()->addSeconds(random_int(1, 300)) : null,
                'parent_run_id' => $i > 0 && (bool) random_int(0, 1) ? $ids[array_rand($ids)] : null,
                'delegation_depth' => random_int(0, 2),
                'cached_prompt_tokens' => random_int(0, 1000),
                'streaming_enabled' => (bool) random_int(0, 1),
                'classifier_result' => $hasClassifier ? $classifiers[array_rand($classifiers)] : null,
                'classifier_tokens' => $hasClassifier ? random_int(100, 1000) : null,
                'created_at' => $startedAt,
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('ai_autopilot_runs', $runs, self::BATCH_SIZE);

        return $ids;
    }

    private function seedAutopilotActions(string $tenantId, array $runIds): void
    {
        $actionTypes = ['send_message' => 50, 'call_tool' => 35, 'wait' => 15];
        $actions = [];

        foreach ($runIds as $runId) {
            $actionCount = random_int(2, 5);
            for ($a = 0; $a < $actionCount; $a++) {
                $actions[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tenant_id' => $tenantId,
                    'run_id' => $runId,
                    'action_type' => PerformanceSeeder::weightedRandom($actionTypes),
                    'input' => json_encode(['param' => 'value']),
                    'output' => json_encode(['result' => 'ok']),
                    'guardrail_result' => (bool) random_int(0, 1) ? json_encode(['passed' => true]) : null,
                    'order' => $a,
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        PerformanceSeeder::insertBatch('ai_autopilot_actions', $actions, self::BATCH_SIZE);
    }

    private function seedAutopilotApprovals(string $tenantId, array $runIds, array $userIds): void
    {
        $statusWeights = ['pending' => 30, 'approved' => 50, 'rejected' => 20];
        $approvals = [];
        $count = min(count($runIds), 10);

        for ($i = 0; $i < $count; $i++) {
            $status = PerformanceSeeder::weightedRandom($statusWeights);
            $isApproved = $status === 'approved';
            $isRejected = $status === 'rejected';

            $approvals[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'run_id' => $runIds[array_rand($runIds)],
                'status' => $status,
                'requested_action' => json_encode(['type' => 'send_message']),
                'approved_by' => $isApproved && ! empty($userIds) ? $userIds[array_rand($userIds)] : null,
                'approved_at' => $isApproved ? now()->subDays(random_int(1, 30)) : null,
                'rejected_at' => $isRejected ? now()->subDays(random_int(1, 30)) : null,
                'rejected_reason' => $isRejected ? 'Motivo da rejeicao '.random_int(1, 100) : null,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        if (! empty($approvals)) {
            PerformanceSeeder::insertBatch('ai_autopilot_approvals', $approvals, self::BATCH_SIZE);
        }
    }

    private function seedUsageLogs(string $tenantId, array $userIds): void
    {
        $features = ['autopilot' => 30, 'rag' => 20, 'chat' => 30, 'transcription' => 10, 'summarization' => 10];
        $models = ['gpt-4o-mini', 'gpt-4o', 'claude-3-5-sonnet', 'claude-3-opus', 'gemini-2.0-flash', 'gemini-2.0-pro'];
        $providers = ['openai' => 40, 'anthropic' => 30, 'google' => 20, 'minimax' => 10];
        $pricingIds = DB::table('ai_model_pricings')->where('is_active', true)->pluck('id')->toArray();

        $logs = [];
        $count = random_int(150, 250);

        for ($i = 0; $i < $count; $i++) {
            $inputTokens = random_int(100, 10000);
            $outputTokens = random_int(100, 5000);
            $inputCost = ($inputTokens / 1_000_000) * 3.00;
            $outputCost = ($outputTokens / 1_000_000) * 15.00;

            $logs[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => ! empty($userIds) ? $userIds[array_rand($userIds)] : null,
                'ai_model_pricing_id' => $pricingIds[array_rand($pricingIds)] ?? null,
                'model_name' => $models[array_rand($models)],
                'provider' => PerformanceSeeder::weightedRandom($providers),
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'input_cost' => $inputCost,
                'output_cost' => $outputCost,
                'request_id' => 'req_'.random_int(100000, 999999),
                'feature' => PerformanceSeeder::weightedRandom($features),
                'latency_ms' => random_int(100, 5000),
                'usable_type' => random_int(0, 1) ? 'Domain\\Chat\\Models\\ChatTicket' : null,
                'usable_id' => random_int(0, 1) ? PerformanceSeeder::uuid() : null,
                'metadata' => json_encode(['version' => '1.0']),
                'cached_prompt_tokens' => random_int(0, 500),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('ai_usage_logs', $logs, self::BATCH_SIZE);
    }

    private function seedSellerNotifications(string $tenantId, array $userIds, array $negotiationIds): void
    {
        $reasons = ['lead_score' => 60, 'follow_up' => 40];
        $channels = ['email' => 70, 'whatsapp' => 20, 'push' => 10];
        $priorities = ['low' => 20, 'normal' => 50, 'high' => 30];
        $notifications = [];
        $count = random_int(15, 25);

        for ($i = 0; $i < $count; $i++) {
            $isDelivered = random_int(0, 100) > 20;
            $isFailed = ! $isDelivered && (bool) random_int(0, 1);

            $notifications[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'seller_id' => ! empty($userIds) ? $userIds[array_rand($userIds)] : null,
                'notifiable_type' => ! empty($negotiationIds) ? 'Domain\\CRM\\Models\\CRMNegotiation' : null,
                'notifiable_id' => ! empty($negotiationIds) ? $negotiationIds[array_rand($negotiationIds)] : null,
                'message' => fake('pt_BR')->sentence(),
                'reason' => PerformanceSeeder::weightedRandom($reasons),
                'channel' => PerformanceSeeder::weightedRandom($channels),
                'priority' => PerformanceSeeder::weightedRandom($priorities),
                'attempts' => random_int(0, 3),
                'scheduled_at' => now()->subDays(random_int(1, 30)),
                'delivered_at' => $isDelivered ? now()->subDays(random_int(1, 30)) : null,
                'failed_at' => $isFailed ? now()->subDays(random_int(1, 30)) : null,
                'error_message' => $isFailed ? 'Falha no envio' : null,
                'metadata' => json_encode(['source' => 'ai_engine']),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('ai_seller_notifications', $notifications, self::BATCH_SIZE);
    }

    private function seedPostSaleSchedules(string $tenantId, array $negotiationIds, array $ticketIds): void
    {
        $types = ['follow_up' => 50, 'nps' => 30, 'renewal' => 20];
        $statusWeights = ['pending' => 40, 'sent' => 40, 'failed' => 20];
        $schedules = [];
        $count = min(count($negotiationIds), 10);

        for ($i = 0; $i < $count; $i++) {
            $status = PerformanceSeeder::weightedRandom($statusWeights);
            $isSent = $status === 'sent';

            $schedules[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'negotiation_id' => $negotiationIds[array_rand($negotiationIds)],
                'ticket_id' => ! empty($ticketIds) ? $ticketIds[array_rand($ticketIds)] : null,
                'schedule_type' => PerformanceSeeder::weightedRandom($types),
                'sale_date' => now()->subDays(random_int(1, 90)),
                'scheduled_at' => now()->subDays(random_int(1, 30)),
                'status' => $status,
                'sent_at' => $isSent ? now()->subDays(random_int(1, 30)) : null,
                'message_id' => $isSent ? PerformanceSeeder::uuid() : null,
                'error_message' => $status === 'failed' ? 'Falha no envio' : null,
                'attempts' => random_int(0, 3),
                'custom_message' => (bool) random_int(0, 1) ? fake('pt_BR')->sentence() : null,
                'metadata' => json_encode(['template' => 'default']),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        if (! empty($schedules)) {
            PerformanceSeeder::insertBatch('ai_post_sale_schedules', $schedules, self::BATCH_SIZE);
        }
    }

    private function seedConversationSummaries(string $tenantId, array $ticketIds): void
    {
        $summaries = [];
        $count = min(count($ticketIds), 20);

        for ($i = 0; $i < $count; $i++) {
            $summaries[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketIds[array_rand($ticketIds)],
                'summary' => fake('pt_BR')->paragraph(),
                'message_count' => random_int(5, 100),
                'generated_at' => PerformanceSeeder::randomDate(),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        if (! empty($summaries)) {
            PerformanceSeeder::insertBatch('ai_conversation_summaries', $summaries, self::BATCH_SIZE);
        }
    }

    private function seedAgentChannels(string $tenantId, array $agentIds): void
    {
        $channels = ['whatsapp' => 70, 'telegram' => 20, 'web' => 10];
        $agentChannels = [];

        foreach ($agentIds as $agentId) {
            $channelCount = random_int(1, 2);
            for ($c = 0; $c < $channelCount; $c++) {
                $agentChannels[] = [
                    'id' => PerformanceSeeder::uuid(),
                    'tenant_id' => $tenantId,
                    'agent_id' => $agentId,
                    'channel' => PerformanceSeeder::weightedRandom($channels),
                    'external_ref' => 'ref_'.random_int(1000, 9999),
                    'is_active' => random_int(0, 100) > 10,
                    'metadata' => json_encode(['instance' => random_int(1, 10)]),
                    'created_at' => PerformanceSeeder::randomDate(),
                    'updated_at' => now(),
                ];
            }
        }

        PerformanceSeeder::insertBatch('ai_agent_channels', $agentChannels, self::BATCH_SIZE);
    }
}
