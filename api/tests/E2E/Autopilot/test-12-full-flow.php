<?php

/**
 * Teste E2E — Full Autopilot Flow
 *
 * Valida o ciclo de vida completo:
 * 1. ToolDispatcherService: catálogo com 29 tools
 * 2. ToolDispatcherService: getToolDefinitions retorna schema OpenAI válido
 * 3. AutopilotRunSnapshotResolver: resolve snapshot (prompt + tools + context)
 * 4. AiAutopilotRun: model persiste e retorna via Eloquent
 * 5. Todas as 29 tool classes existem e instanciam sem exceção
 */

declare(strict_types=1);

use Domain\Ai\Enums\AiAgentRole;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Services\AutopilotRunSnapshotResolver;
use Domain\Ai\Services\ToolDispatcherService;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

e2e_group('12 · Full Autopilot Flow');

$ctx = require __DIR__.'/setup.php';

// ── Tool Catalog ──────────────────────────────────────────────────────────────

e2e_run('ToolDispatcherService: catálogo retorna 29 tools para tenant', function () use ($ctx): void {
    $dispatcher = app(ToolDispatcherService::class);
    $catalog = $dispatcher->getCatalog($ctx['tenant_id']);

    e2e_assert(count($catalog) === 29, 'catálogo tem 29 tools (got: '.count($catalog).')');

    foreach ($catalog as $item) {
        e2e_assert(isset($item['name']), 'name presente em: '.json_encode($item));
        e2e_assert(isset($item['handler_class']), "handler_class presente em: {$item['name']}");
        e2e_assert(class_exists($item['handler_class']), "classe existe: {$item['handler_class']}");
    }
});

// ── Tool Definitions (OpenAI Schema) ─────────────────────────────────────────

e2e_run('ToolDispatcherService: getToolDefinitions retorna schema OpenAI válido', function () use ($ctx): void {
    $dispatcher = app(ToolDispatcherService::class);
    $definitions = $dispatcher->getToolDefinitions($ctx['tenant_id'], $ctx['agent_id'] ?? null);

    e2e_assert(count($definitions) > 0, 'tem pelo menos 1 tool definida');

    foreach ($definitions as $def) {
        e2e_assert($def['type'] === 'function', "type=function para {$def['function']['name']}");
        e2e_assert(isset($def['function']['name']), 'function.name presente');
        e2e_assert(isset($def['function']['description']), "function.description presente em {$def['function']['name']}");
        e2e_assert(isset($def['function']['parameters']), "function.parameters presente em {$def['function']['name']}");
    }
});

// ── Tool Instantiation ────────────────────────────────────────────────────────

e2e_run('Todas as 29 tool classes instanciam sem exceção via app()', function (): void {
    $toolNames = [
        'send_message', 'transfer_to_human', 'close_ticket', 'read_ticket',
        'create_contact', 'get_contact_info', 'update_contact', 'update_contact_tags',
        'search_contacts', 'link_contact_to_company',
        'create_company', 'update_company',
        'create_negotiation', 'get_negotiation_info', 'move_pipeline',
        'update_lead_score', 'qualify_lead', 'close_negotiation', 'add_product_to_negotiation',
        'create_proposal', 'list_products', 'list_funnel_steps',
        'search_knowledge',
        'check_availability', 'schedule_event',
        'create_task', 'create_note',
        'notify_seller',
        'delegate_to_agent',
    ];

    e2e_assert(count($toolNames) === 29, '29 tools na lista');

    foreach ($toolNames as $toolName) {
        $className = 'Domain\\Ai\\Tools\\'.Str::studly($toolName).'Tool';
        e2e_assert(class_exists($className), "classe existe: {$className}");

        $instance = app($className);
        e2e_assert($instance !== null, "instância criada: {$className}");
        e2e_assert($instance->getName() === $toolName, "getName()={$toolName} (got: {$instance->getName()})");
        e2e_assert((string) $instance->getDescription() !== '', "getDescription() não vazio: {$toolName}");
        e2e_assert(is_array($instance->getParameters()), "getParameters() retorna array: {$toolName}");
    }
});

// ── Snapshot Resolver ─────────────────────────────────────────────────────────

e2e_run('AutopilotRunSnapshotResolver: resolve snapshot sem exceção', function () use ($ctx): void {
    $resolver = app(AutopilotRunSnapshotResolver::class);
    $agent = \Domain\Ai\Models\AiAgent::query()->find($ctx['agent_id']);

    e2e_assert($agent !== null, 'agente E2E encontrado');

    $snapshot = $resolver->resolve($ctx['tenant_id'], $agent, $ctx['ticket_id']);

    e2e_assert(is_array($snapshot), 'snapshot é array');
    e2e_assert(array_key_exists('prompt', $snapshot), 'snapshot.prompt existe (pode ser null)');
    e2e_assert(array_key_exists('tools', $snapshot), 'snapshot.tools existe (pode ser null)');
    e2e_assert(array_key_exists('context', $snapshot), 'snapshot.context existe (pode ser null)');
    e2e_assert(isset($snapshot['hydrated_at']), 'snapshot.hydrated_at presente');
    e2e_assert((string) $snapshot['hydrated_at'] !== '', 'hydrated_at não vazio');

    if ($snapshot['tools'] !== null) {
        e2e_assert(is_array($snapshot['tools']), 'snapshot.tools é array quando presente');
        e2e_assert(count($snapshot['tools']) > 0, 'snapshot.tools tem ao menos 1 tool');
    }
});

// ── AiAutopilotRun Model ──────────────────────────────────────────────────────

e2e_run('AiAutopilotRun: persiste e recupera model com campos corretos', function () use ($ctx): void {
    $runId = (string) Str::orderedUuid();

    $run = AiAutopilotRun::query()->create([
        'id' => $runId,
        'tenant_id' => $ctx['tenant_id'],
        'playbook_id' => $ctx['playbook_id'],
        'status' => 'queued',
        'input_context' => ['messages' => [], 'ticket_id' => $ctx['ticket_id']],
    ]);

    e2e_assert($run->id === $runId, 'id persiste corretamente');
    e2e_assert($run->status === 'queued', 'status=queued');
    e2e_assert(is_array($run->input_context), 'input_context cast para array');
    e2e_assert($run->tenant_id === $ctx['tenant_id'], 'tenant_id correto');

    // Atualiza status → running
    $run->update(['status' => 'running', 'started_at' => now()]);
    $run->refresh();

    e2e_assert($run->status === 'running', "status=running após update (got: {$run->status})");
    e2e_assert($run->started_at !== null, 'started_at preenchido');

    // Atualiza → completed com output
    $run->update([
        'status' => 'completed',
        'completed_at' => now(),
        'output' => [
            'response' => 'Olá, como posso ajudar?',
            'tool_trace' => [],
            'tokens' => ['input' => 100, 'output' => 50, 'total' => 150],
        ],
    ]);
    $run->refresh();

    e2e_assert($run->status === 'completed', 'status=completed');
    e2e_assert(is_array($run->output), 'output cast para array');
    e2e_assert(isset($run->output['response']), 'output.response presente');
    e2e_assert(isset($run->output['tokens']), 'output.tokens presente');

    // Cleanup
    $run->forceDelete();
});

// ── Permission Matrix Service ─────────────────────────────────────────────────

e2e_run('AiPermissionMatrixService: todas as roles retornam lista não-vazia', function (): void {
    $service = app(\Domain\Ai\Services\AiPermissionMatrixService::class);

    foreach (AiAgentRole::cases() as $role) {
        $tools = $service->getAvailableTools($role);
        e2e_assert(count($tools) > 0, "role={$role->value} tem tools definidas");
        e2e_assert(count($tools) <= 32, "role={$role->value} não excede 32 tools (got: ".count($tools).')');
    }
});

e2e_run('AiPermissionMatrixService: general tem 32 tools (máximo)', function (): void {
    $service = app(\Domain\Ai\Services\AiPermissionMatrixService::class);
    $tools = $service->getAvailableTools(AiAgentRole::GENERAL);

    e2e_assert(count($tools) === 32, '32 tools para role=general (got: '.count($tools).')');
});
