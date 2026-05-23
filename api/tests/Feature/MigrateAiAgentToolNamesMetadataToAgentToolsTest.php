<?php

declare(strict_types=1);

use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(LazilyRefreshDatabase::class);

/**
 * Helper: garante que a migration pode ser executada (remove do registro se já rodou).
 */
function resetMigration(string $migrationClass): void
{
    $migrationFile = '2026_05_19_091500_migrate_ai_agent_tool_names_metadata_to_agent_tools';
    DB::table('migrations')->where('migration', $migrationFile)->delete();
}

beforeEach(function (): void {
    $this->tenantA = PlatformTenant::factory()->create(['name' => 'Tenant A']);
    $this->tenantB = PlatformTenant::factory()->create(['name' => 'Tenant B']);

    // Tools do Tenant A
    $this->toolA1 = \Domain\Ai\Models\AiAutopilotTool::query()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'search_knowledge_base',
        'display_name' => 'Search KB',
        'is_active' => true,
    ]);

    $this->toolA2 = \Domain\Ai\Models\AiAutopilotTool::query()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'create_ticket',
        'display_name' => 'Create Ticket',
        'is_active' => true,
    ]);

    // Tools do Tenant B
    $this->toolB1 = \Domain\Ai\Models\AiAutopilotTool::query()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'search_knowledge_base',
        'display_name' => 'Search KB',
        'is_active' => true,
    ]);

    // Agent do Tenant A com tool_names no metadata
    $this->agentA = \Domain\Ai\Models\AiAgent::query()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Agent A',
        'type' => 'support',
        'metadata' => [
            'tool_names' => ['search_knowledge_base', 'create_ticket', 'nonexistent_tool'],
            'custom_key' => 'custom_value',
            'nested' => ['foo' => 'bar'],
        ],
    ]);

    // Agent do Tenant B com tool_names no metadata
    $this->agentB = \Domain\Ai\Models\AiAgent::query()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Agent B',
        'type' => 'sales',
        'metadata' => [
            'tool_names' => ['search_knowledge_base'],
            'other_data' => 123,
        ],
    ]);
});

/**
 * Run the migration ensuring it's not already marked as done.
 */
function runMigration(\PHPUnit\Framework\TestCase $testCase): void
{
    resetMigration('MigrateAiAgentToolNamesMetadataToAgentTools');
    $testCase->artisan('migrate', ['--path' => 'database/migrations/2026_05_19_091500_migrate_ai_agent_tool_names_metadata_to_agent_tools.php'])->assertSuccessful();
}

/**
 * Teste 1: Backfill de metadata.tool_names para ai_agent_tools
 */
test('backfills metadata.tool_names to ai_agent_tools', function (): void {
    runMigration($this);

    // Agent A: 2 tools encontradas (search_knowledge_base + create_ticket)
    $agentATools = DB::table('ai_agent_tools')
        ->where('agent_id', $this->agentA->id)
        ->get();

    expect($agentATools)->toHaveCount(2);
    expect($agentATools->pluck('tool_id')->toArray())->toContain($this->toolA1->id, $this->toolA2->id);

    // Agent B: 1 tool encontrada
    $agentBTools = DB::table('ai_agent_tools')
        ->where('agent_id', $this->agentB->id)
        ->get();

    expect($agentBTools)->toHaveCount(1);
    expect($agentBTools->first()->tool_id)->toBe($this->toolB1->id);
});

/**
 * Teste 2: Preservação de outras chaves de metadata
 */
test('preserves other metadata keys after migration', function (): void {
    runMigration($this);

    $this->agentA->refresh();

    expect($this->agentA->metadata)->not->toHaveKey('tool_names');
    expect($this->agentA->metadata)->toHaveKey('custom_key', 'custom_value');
    expect($this->agentA->metadata)->toHaveKey('nested', ['foo' => 'bar']);

    $this->agentB->refresh();

    expect($this->agentB->metadata)->not->toHaveKey('tool_names');
    expect($this->agentB->metadata)->toHaveKey('other_data', 123);
});

/**
 * Teste 3: Nomes inexistentes em ai_autopilot_tools não quebram a execução
 */
test('nonexistent tool names do not break migration', function (): void {
    Log::spy();

    runMigration($this);

    // Apenas 2 tools foram criadas para o Agent A (a terceira é inexistente)
    $agentATools = DB::table('ai_agent_tools')
        ->where('agent_id', $this->agentA->id)
        ->get();

    expect($agentATools)->toHaveCount(2);

    // Warning foi logado para a tool inexistente
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'nonexistent_tool'));
});

/**
 * Teste 4: Isolamento — tools do Tenant A não são vinculadas a agentes do Tenant B
 */
test('tenant isolation: tools from tenant A are not linked to tenant B agents', function (): void {
    runMigration($this);

    // Agent B deve ter apenas a tool do Tenant B
    $agentBToolIds = DB::table('ai_agent_tools')
        ->where('agent_id', $this->agentB->id)
        ->pluck('tool_id')
        ->toArray();

    expect($agentBToolIds)->not->toContain($this->toolA1->id);
    expect($agentBToolIds)->not->toContain($this->toolA2->id);
    expect($agentBToolIds)->toContain($this->toolB1->id);

    // Agent A deve ter apenas tools do Tenant A
    $agentAToolIds = DB::table('ai_agent_tools')
        ->where('agent_id', $this->agentA->id)
        ->pluck('tool_id')
        ->toArray();

    expect($agentAToolIds)->not->toContain($this->toolB1->id);
    expect($agentAToolIds)->toContain($this->toolA1->id, $this->toolA2->id);
});

/**
 * Teste 5: Idempotência — reexecutar a migration não duplica registros
 */
test('migration is idempotent and does not duplicate records', function (): void {
    runMigration($this);

    // Re-executar a migration (simulando rodar novamente)
    runMigration($this);

    $agentATools = DB::table('ai_agent_tools')
        ->where('agent_id', $this->agentA->id)
        ->get();

    expect($agentATools)->toHaveCount(2);
});

/**
 * Teste 6: Agent sem metadata.tool_names não é afetado
 */
test('agent without tool_names in metadata is not affected', function (): void {
    $agentNoTools = \Domain\Ai\Models\AiAgent::query()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Agent No Tools',
        'type' => 'support',
        'metadata' => ['some_key' => 'some_value'],
    ]);

    runMigration($this);

    $agentNoTools->refresh();

    expect($agentNoTools->metadata)->toHaveKey('some_key', 'some_value');

    $tools = DB::table('ai_agent_tools')
        ->where('agent_id', $agentNoTools->id)
        ->get();

    expect($tools)->toHaveCount(0);
});

/**
 * Teste 7: Agent com metadata vazio ou nulo não quebra
 */
test('agent with null metadata does not break migration', function (): void {
    $agentNullMetadata = \Domain\Ai\Models\AiAgent::query()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Agent Null Metadata',
        'type' => 'support',
        'metadata' => null,
    ]);

    runMigration($this);

    $agentNullMetadata->refresh();

    expect($agentNullMetadata->metadata)->toBeNull();
});

/**
 * Teste 8: Rollback restaura tool_names no metadata e remove pivots
 */
test('rollback restores tool_names in metadata and removes pivots', function (): void {
    runMigration($this);

    // Verificar que a migração foi aplicada
    $this->agentA->refresh();
    expect($this->agentA->metadata)->not->toHaveKey('tool_names');

    // Debug: check pivots before rollback
    $pivotsBefore = DB::table('ai_agent_tools')->where('agent_id', $this->agentA->id)->get();
    expect($pivotsBefore)->toHaveCount(2);

    /** @var \Illuminate\Database\Migrations\Migration $migration */
    $migration = require base_path('database/migrations/2026_05_19_091500_migrate_ai_agent_tool_names_metadata_to_agent_tools.php');
    $migration->down();

    // Debug: check pivots after rollback
    $pivotsAfter = DB::table('ai_agent_tools')->where('agent_id', $this->agentA->id)->get();

    $this->agentA->refresh();

    expect($this->agentA->metadata)->toHaveKey('tool_names');
    expect($this->agentA->metadata['tool_names'])->toContain('search_knowledge_base', 'create_ticket');
    expect($this->agentA->metadata)->toHaveKey('custom_key', 'custom_value');

    // Pivots foram removidos
    expect($pivotsAfter)->toHaveCount(0);
});
