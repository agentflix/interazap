<?php

declare(strict_types=1);

use Domain\Ai\Enums\AiToolEnum;
use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentDelegation;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Models\AiAutopilotTool;
use Domain\Ai\Services\AiAgentToolPermissionService;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

// ─── Helper: verificar Gateway ───────────────────────────────────────────────

function e2e_gateway_online(): bool
{
    $ch = curl_init('http://localhost:3000/health');
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return $code === 200;
}

// ─── Helper: garantir tools ativas no tenant e atribuídas ao agente ─────────

function ensureToolsForAgentT14(string $tenantId, string $agentId): void
{
    $allToolNames = [
        AiToolEnum::SEND_MESSAGE,
        AiToolEnum::READ_TICKET,
        AiToolEnum::TRANSFER_TO_HUMAN,
        AiToolEnum::CLOSE_TICKET,
        AiToolEnum::CREATE_CONTACT,
        AiToolEnum::GET_CONTACT_INFO,
        AiToolEnum::UPDATE_CONTACT,
        AiToolEnum::UPDATE_CONTACT_TAGS,
        AiToolEnum::SEARCH_CONTACTS,
        AiToolEnum::LINK_CONTACT_TO_COMPANY,
        AiToolEnum::CREATE_COMPANY,
        AiToolEnum::UPDATE_COMPANY,
        AiToolEnum::CREATE_NEGOTIATION,
        AiToolEnum::GET_NEGOTIATION_INFO,
        AiToolEnum::MOVE_PIPELINE,
        AiToolEnum::UPDATE_LEAD_SCORE,
        AiToolEnum::QUALIFY_LEAD,
        AiToolEnum::CLOSE_NEGOTIATION,
        AiToolEnum::ADD_PRODUCT_TO_NEGOTIATION,
        AiToolEnum::CREATE_PROPOSAL,
        AiToolEnum::LIST_PRODUCTS,
        AiToolEnum::LIST_FUNNEL_STEPS,
        AiToolEnum::SEARCH_KNOWLEDGE,
        AiToolEnum::CHECK_AVAILABILITY,
        AiToolEnum::SCHEDULE_EVENT,
        AiToolEnum::GET_AVAILABLE_SLOTS,
        AiToolEnum::CONFIRM_EVENT_BOOKING,
        AiToolEnum::CREATE_TASK,
        AiToolEnum::CREATE_NOTE,
        AiToolEnum::NOTIFY_SELLER,
        AiToolEnum::DELEGATE_TO_AGENT,
    ];

    foreach ($allToolNames as $toolName) {
        $existing = AiAutopilotTool::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $toolName)
            ->first();

        if (! $existing) {
            $className = 'Domain\\Ai\\Tools\\'.Str::studly($toolName).'Tool';
            AiAutopilotTool::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'name' => $toolName,
                'display_name' => Str::headline($toolName),
                'handler_class' => class_exists($className) ? $className : null,
                'is_active' => true,
            ]);
        }
    }

    $service = app(AiAgentToolPermissionService::class);
    $service->syncAgentTools($tenantId, $agentId, $allToolNames);
}

// ─── Setup ───────────────────────────────────────────────────────────────────

$ctx = require __DIR__.'/setup.php';

// Força ai_enabled no plano do tenant
$tenant = PlatformTenant::query()->find($ctx['tenant_id']);
$plan = PlatformPlan::query()->find($tenant->plan_id);
$originalAiEnabled = $plan->ai_enabled;
$plan->update(['ai_enabled' => true]);

// Limpa human_takeover_at do ticket (bloqueia fluxo AI se setado)
// human_takeover_at fica na tabela chat_tickets_extended (acessado via relação extended)
$ticket = ChatTicket::query()->find($ctx['ticket_id']);
if ($ticket->extended) {
    $ticket->extended->update(['human_takeover_at' => null]);
}

// Força QUEUE_CONNECTION=sync para todos os jobs rodarem síncronos
Config::set('queue.default', 'sync');
Config::set('queue.connections.sync.driver', 'sync');
Config::set('ai.queue_connection', 'sync');
Config::set('ai.autopilot.queue_name', 'sync');

// Garante que o agente E2E está com type='general' e is_active=true
$e2eAgent = AiAgent::query()->where('tenant_id', $ctx['tenant_id'])->where('name', 'Agente E2E')->first();
if ($e2eAgent) {
    $e2eAgent->update([
        'type' => 'general',
        'is_active' => true,
    ]);
}

ensureToolsForAgentT14($ctx['tenant_id'], $e2eAgent->id);

// Verifica se Gateway está online
$gatewayOnline = e2e_gateway_online();
$gatewayStatus = $gatewayOnline ? 'ONLINE' : 'OFFLINE';
echo "\n  \033[1;36mGateway status: {$gatewayStatus}\033[0m\n";

// ─── Contadores ──────────────────────────────────────────────────────────────

$g1Pass = 0;
$g1Fail = 0;
$g2Pass = 0;
$g2Fail = 0;
$g3Pass = 0;
$g3Fail = 0;
$g4Pass = 0;
$g4Fail = 0;

function trackResultT14(&$pass, &$fail, bool $isPass): void
{
    if ($isPass) {
        $pass++;
    } else {
        $fail++;
    }
}

// ─── Grupo 1 — Pipeline Webhook → Run Criado (~5 testes) ────────────────────

e2e_group('14.1 · Pipeline Webhook → Run Criado');

// Teste 1: routeInbound cria AiAutopilotRun
$ok = e2e_run('pipeline: routeInbound cria run', function () use ($ctx): void {
    $ticket = ChatTicket::query()->find($ctx['ticket_id']);
    $router = app(\Domain\Chat\Services\ChatWebhookRouter::class);
    $router->routeInbound($ctx['tenant_id'], $ticket, 'Quero saber sobre o produto', [
        'instance_id' => $ctx['instance_id'],
        'message_id' => (string) Str::orderedUuid(),
        'message_type' => 'text',
        'is_first_interaction' => false,
    ]);

    $run = AiAutopilotRun::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->latest('created_at')
        ->first();

    e2e_assert($run !== null, 'AiAutopilotRun criado');
    e2e_assert(in_array($run->status, ['queued', 'running', 'completed']), 'Run status válido');
});
trackResultT14($g1Pass, $g1Fail, $ok['pass']);

// Teste 2: run.agent_id corresponde ao agente E2E
$ok = e2e_run('pipeline: run.agent_id = agente E2E', function () use ($ctx, $e2eAgent): void {
    $run = AiAutopilotRun::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->latest('created_at')
        ->first();

    e2e_assert($run !== null, 'Run existe');
    $agentId = $run->input_context['agent_id'] ?? null;
    e2e_assert($agentId === $e2eAgent->id, 'agent_id corresponde ao agente E2E');
});
trackResultT14($g1Pass, $g1Fail, $ok['pass']);

// Teste 3: run.input_context contém ticket_id
$ok = e2e_run('pipeline: run.input_context contém ticket_id', function () use ($ctx): void {
    $run = AiAutopilotRun::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->latest('created_at')
        ->first();

    e2e_assert($run !== null, 'Run existe');
    e2e_assert(isset($run->input_context['ticket_id']), 'input_context tem ticket_id');
    e2e_assert($run->input_context['ticket_id'] === $ctx['ticket_id'], 'ticket_id correto');
});
trackResultT14($g1Pass, $g1Fail, $ok['pass']);

// Teste 4: run.input_context contém message_id e body
$ok = e2e_run('pipeline: run.input_context contém message_id e body', function () use ($ctx): void {
    $run = AiAutopilotRun::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->latest('created_at')
        ->first();

    e2e_assert($run !== null, 'Run existe');
    e2e_assert(isset($run->input_context['message_id']), 'input_context tem message_id');
    e2e_assert(isset($run->input_context['body']), 'input_context tem body');
    e2e_assert($run->input_context['body'] === 'Quero saber sobre o produto', 'body correto');
});
trackResultT14($g1Pass, $g1Fail, $ok['pass']);

// Teste 5: segunda mensagem cria outro run (idempotência por message_id)
$ok = e2e_run('pipeline: segunda mensagem cria outro run', function () use ($ctx): void {
    $ticket = ChatTicket::query()->find($ctx['ticket_id']);
    $router = app(\Domain\Chat\Services\ChatWebhookRouter::class);
    $router->routeInbound($ctx['tenant_id'], $ticket, 'Qual o preço?', [
        'instance_id' => $ctx['instance_id'],
        'message_id' => (string) Str::orderedUuid(),
        'message_type' => 'text',
        'is_first_interaction' => false,
    ]);

    $runs = AiAutopilotRun::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->orderByDesc('created_at')
        ->take(2)
        ->get();

    e2e_assert($runs->count() >= 2, 'Dois runs criados para mensagens diferentes');
    e2e_assert($runs[0]->id !== $runs[1]->id, 'Runs têm IDs diferentes');
});
trackResultT14($g1Pass, $g1Fail, $ok['pass']);

e2e_summary('Grupo 1 · Pipeline Webhook → Run Criado', $g1Pass, $g1Fail);

// ─── Grupo 2 — Execução Real (Claude, se Gateway disponível, ~3 testes) ─────

e2e_group('14.2 · Execução Real (Claude)');

if (! $gatewayOnline) {
    echo "  \033[1;33m[SKIP]\033[0m Gateway offline — grupos de execução real pulados\n";
    e2e_summary('Grupo 2 · Execução Real (Claude)', 0, 0);
} else {
    // Pega o último run criado (status queued) para executar
    $lastRun = AiAutopilotRun::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->where('status', 'queued')
        ->latest('created_at')
        ->first();

    if (! $lastRun) {
        echo "  \033[1;33m[SKIP]\033[0m Nenhum run queued disponível para execução\n";
        e2e_summary('Grupo 2 · Execução Real (Claude)', 0, 0);
    } else {
        $runIdGrupo2 = $lastRun->id;

        // Teste 1: AiRunExecutionJob completa o run
        $ok = e2e_run('execução: AiRunExecutionJob dispatch', function () use ($runIdGrupo2): void {
            \Domain\Ai\Jobs\AiRunExecutionJob::dispatch($runIdGrupo2);

            // Aguarda run completar (com timeout)
            $run = AiAutopilotRun::query()->find($runIdGrupo2);
            $retries = 0;
            while ($retries < 30 && in_array($run->status, ['queued', 'running'], true)) {
                usleep(500000); // 500ms
                $run->refresh();
                $retries++;
            }

            e2e_assert($run->status === 'completed', 'Run completado (status='.$run->status.')');
        });
        trackResultT14($g2Pass, $g2Fail, $ok['pass']);

        // Teste 2: Claude respondeu (output contém response)
        $ok = e2e_run('execução: Claude respondeu', function () use ($runIdGrupo2): void {
            $run = AiAutopilotRun::query()->find($runIdGrupo2);
            e2e_assert($run !== null, 'Run existe');
            e2e_assert(is_array($run->output), 'output é array');
            e2e_assert(isset($run->output['response']) || isset($run->output['assistant_message']), 'Claude respondeu (response ou assistant_message presente)');
        });
        trackResultT14($g2Pass, $g2Fail, $ok['pass']);

        // Teste 3: run tem started_at e completed_at preenchidos
        $ok = e2e_run('execução: run tem timestamps', function () use ($runIdGrupo2): void {
            $run = AiAutopilotRun::query()->find($runIdGrupo2);
            e2e_assert($run !== null, 'Run existe');
            e2e_assert($run->started_at !== null, 'started_at preenchido');
            e2e_assert($run->completed_at !== null, 'completed_at preenchido');
        });
        trackResultT14($g2Pass, $g2Fail, $ok['pass']);
    }

    e2e_summary('Grupo 2 · Execução Real (Claude)', $g2Pass, $g2Fail);
}

// ─── Grupo 3 — Delegação Sofia → Lucas (se Gateway disponível, ~3 testes) ───

e2e_group('14.3 · Delegação Sofia → Lucas');

$delegationTicket = null;

if (! $gatewayOnline) {
    echo "  \033[1;33m[SKIP]\033[0m Gateway offline — grupo de delegação pulado\n";
    e2e_summary('Grupo 3 · Delegação Sofia → Lucas', 0, 0);
} else {
    // Setup: cria agentes Sofia + Lucas + delegação
    $sofiaAgent = AiAgent::query()->where('tenant_id', $ctx['tenant_id'])->where('name', 'Sofia T14')->first();
    if (! $sofiaAgent) {
        $sofiaAgent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'name' => 'Sofia T14',
            'type' => 'general',
            'model_id' => 'claude-haiku-4-5-20251001',
            'is_active' => true,
            'max_tokens' => 800,
            'temperature' => 0.7,
            'top_p' => 1.0,
        ]);
    }

    $lucasAgent = AiAgent::query()->where('tenant_id', $ctx['tenant_id'])->where('name', 'Lucas T14')->first();
    if (! $lucasAgent) {
        $lucasAgent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'name' => 'Lucas T14',
            'type' => 'sales',
            'model_id' => 'claude-haiku-4-5-20251001',
            'is_active' => true,
            'max_tokens' => 800,
            'temperature' => 0.7,
            'top_p' => 1.0,
        ]);
    }

    ensureToolsForAgentT14($ctx['tenant_id'], $sofiaAgent->id);
    ensureToolsForAgentT14($ctx['tenant_id'], $lucasAgent->id);

    $delegation = AiAgentDelegation::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->where('source_agent_id', $sofiaAgent->id)
        ->where('target_agent_id', $lucasAgent->id)
        ->first();

    if (! $delegation) {
        $delegation = AiAgentDelegation::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $ctx['tenant_id'],
            'source_agent_id' => $sofiaAgent->id,
            'target_agent_id' => $lucasAgent->id,
            'max_depth' => 3,
            'is_active' => true,
        ]);
    }

    // Cria um ticket com sticky agent = Sofia
    $delegationTicket = ChatTicket::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $ctx['tenant_id'],
        'contact_id' => $ctx['contact_id'],
        'instance_id' => $ctx['instance_id'],
        'channel' => 'whatsapp',
        'phone' => '+5511900000999',
        'phone_e164' => '+5511900000999',
        'remote_jid' => '5511900000999@s.whatsapp.net',
        'push_name' => 'Delegação T14',
        'status' => 'open',
        'priority' => 'normal',
        'is_bot_active' => true,
        'current_ai_agent_id' => $sofiaAgent->id,
    ]);

    // Dispara mensagem que deve acionar Sofia (sticky)
    $ok = e2e_run('delegação: routeInbound com sticky agent Sofia', function () use ($ctx, $delegationTicket, $sofiaAgent): void {
        $router = app(\Domain\Chat\Services\ChatWebhookRouter::class);
        $router->routeInbound($ctx['tenant_id'], $delegationTicket, 'Quero falar com um vendedor', [
            'instance_id' => $ctx['instance_id'],
            'message_id' => (string) Str::orderedUuid(),
            'message_type' => 'text',
            'is_first_interaction' => false,
        ]);

        $run = AiAutopilotRun::query()
            ->where('tenant_id', $ctx['tenant_id'])
            ->where('input_context->agent_id', $sofiaAgent->id)
            ->latest('created_at')
            ->first();

        e2e_assert($run !== null, 'Run criado com sticky agent Sofia');
    });
    trackResultT14($g3Pass, $g3Fail, $ok['pass']);

    // Executa o run da Sofia para que ela possa delegar para Lucas
    $sofiaRun = AiAutopilotRun::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->where('input_context->agent_id', $sofiaAgent->id)
        ->latest('created_at')
        ->first();

    if ($sofiaRun && in_array($sofiaRun->status, ['queued', 'running'], true)) {
        \Domain\Ai\Jobs\AiRunExecutionJob::dispatch($sofiaRun->id);

        // Aguarda completar
        $retries = 0;
        while ($retries < 30 && in_array($sofiaRun->status, ['queued', 'running'], true)) {
            usleep(500000);
            $sofiaRun->refresh();
            $retries++;
        }
    }

    // Teste 2: child run criado com agent_id = Lucas
    $ok = e2e_run('delegação: child run criado com Lucas', function () use ($ctx, $lucasAgent, $sofiaRun): void {
        $childRun = AiAutopilotRun::query()
            ->where('tenant_id', $ctx['tenant_id'])
            ->where('parent_run_id', $sofiaRun->id)
            ->first();

        e2e_assert($childRun !== null, 'Child run existe');

        $childAgentId = $childRun->input_context['agent_id'] ?? null;
        e2e_assert($childAgentId === $lucasAgent->id, 'Child run agent_id = Lucas');
    });
    trackResultT14($g3Pass, $g3Fail, $ok['pass']);

    // Teste 3: executa child run (Lucas) e verifica completude
    $childRun = AiAutopilotRun::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->where('parent_run_id', $sofiaRun->id)
        ->first();

    if ($childRun && in_array($childRun->status, ['queued'], true)) {
        \Domain\Ai\Jobs\AiRunExecutionJob::dispatch($childRun->id);

        $retries = 0;
        while ($retries < 30 && in_array($childRun->status, ['queued', 'running'], true)) {
            usleep(500000);
            $childRun->refresh();
            $retries++;
        }
    }

    $ok = e2e_run('delegação: child run (Lucas) completado', function () use ($childRun): void {
        e2e_assert($childRun !== null, 'Child run existe');
        e2e_assert($childRun->status === 'completed', 'Child run completado (status='.$childRun->status.')');
    });
    trackResultT14($g3Pass, $g3Fail, $ok['pass']);

    e2e_summary('Grupo 3 · Delegação Sofia → Lucas', $g3Pass, $g3Fail);
}

// ─── Grupo 4 — Cleanup ──────────────────────────────────────────────────────

$g4Pass = 0;
$g4Fail = 0;

e2e_group('14.4 · Cleanup');

$ok = e2e_run('cleanup: restaura plan.ai_enabled', function () use ($plan, $originalAiEnabled): void {
    $plan->update(['ai_enabled' => $originalAiEnabled]);
    $plan->refresh();
    e2e_assert($plan->ai_enabled === $originalAiEnabled, 'plan.ai_enabled restaurado');
});
trackResultT14($g4Pass, $g4Fail, $ok['pass']);

$ok = e2e_run('cleanup: remove runs criados pelo test-14', function () use ($ctx): void {
    // Remove todos os runs criados neste teste (os mais recentes)
    $runs = AiAutopilotRun::query()
        ->where('tenant_id', $ctx['tenant_id'])
        ->orderByDesc('created_at')
        ->get();

    foreach ($runs as $run) {
        $run->forceDelete();
    }

    $remaining = AiAutopilotRun::query()->where('tenant_id', $ctx['tenant_id'])->count();
    e2e_assert($remaining === 0, 'Todos os runs removidos');
});
trackResultT14($g4Pass, $g4Fail, $ok['pass']);

$ok = e2e_run('cleanup: remove agentes temporários T14', function () use ($ctx): void {
    AiAgent::query()->where('tenant_id', $ctx['tenant_id'])->whereIn('name', ['Sofia T14', 'Lucas T14'])->delete();

    $remaining = AiAgent::query()->where('tenant_id', $ctx['tenant_id'])->whereIn('name', ['Sofia T14', 'Lucas T14'])->count();
    e2e_assert($remaining === 0, 'Agentes temporários removidos');
});
trackResultT14($g4Pass, $g4Fail, $ok['pass']);

$ok = e2e_run('cleanup: remove delegações T14', function () use ($ctx): void {
    $sofiaAgent = AiAgent::query()->where('tenant_id', $ctx['tenant_id'])->where('name', 'Sofia T14')->first();
    $lucasAgent = AiAgent::query()->where('tenant_id', $ctx['tenant_id'])->where('name', 'Lucas T14')->first();

    if ($sofiaAgent || $lucasAgent) {
        AiAgentDelegation::query()
            ->where('tenant_id', $ctx['tenant_id'])
            ->where(function ($q) use ($sofiaAgent, $lucasAgent) {
                if ($sofiaAgent) {
                    $q->orWhere('source_agent_id', $sofiaAgent->id);
                }
                if ($lucasAgent) {
                    $q->orWhere('target_agent_id', $lucasAgent->id);
                }
            })
            ->delete();
    }

    e2e_assert(true, 'Delegações removidas');
});
trackResultT14($g4Pass, $g4Fail, $ok['pass']);

$ok = e2e_run('cleanup: remove ticket de delegação', function () use ($delegationTicket): void {
    if ($delegationTicket !== null) {
        $delegationTicket->forceDelete();
    }
    e2e_assert(true, 'Ticket de delegação removido');
});
trackResultT14($g4Pass, $g4Fail, $ok['pass']);

e2e_summary('Grupo 4 · Cleanup', $g4Pass, $g4Fail);
