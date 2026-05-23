<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotTool;
use Domain\Ai\Services\AutopilotRunSnapshotResolver;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Testes do AutopilotRunSnapshotResolver.
 *
 * Cobre a resolução de prompt, context e tools para snapshots de runs,
 * com foco na resolução de tools via pivot `ai_agent_tools`.
 *
 * @group ai
 * @group ai-snapshot
 */
class AutopilotRunSnapshotResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AutopilotRunSnapshotResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(AutopilotRunSnapshotResolver::class);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Cria um tenant.
     */
    private function createTenant(): PlatformTenant
    {
        return PlatformTenant::factory()->create();
    }

    /**
     * Cria um agent.
     */
    private function createAgent(string $tenantId, string $name = 'Test Agent'): AiAgent
    {
        return AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => $name,
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);
    }

    /**
     * Cria uma tool ativa.
     */
    private function createTool(
        string $tenantId,
        string $name,
        bool $isActive = true,
    ): AiAutopilotTool {
        return AiAutopilotTool::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => $name,
            'display_name' => $name,
            'description' => "Tool: {$name}",
            'parameters_schema' => ['type' => 'object'],
            'is_system' => false,
            'is_active' => $isActive,
        ]);
    }

    /**
     * Vincula tool ao agent na pivot.
     */
    private function attachTool(AiAgent $agent, AiAutopilotTool $tool): void
    {
        DB::table('ai_agent_tools')->insert([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $agent->tenant_id,
            'agent_id' => (string) $agent->id,
            'tool_id' => (string) $tool->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // =========================================================================
    // resolveTools — tool presente em ai_agent_tools aparece no snapshot
    // =========================================================================

    public function test_snapshot_includes_tool_definition_from_pivot(): void
    {
        $tenant = $this->createTenant();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);

        // Tool com handler existente: SearchKnowledge → SearchKnowledgeTool
        $tool = $this->createTool($tenantId, 'SearchKnowledge', isActive: true);
        $this->attachTool($agent, $tool);

        $snapshot = $this->resolver->resolve($tenantId, $agent, '');

        expect($snapshot)
            ->toHaveKey('tools')
            ->and($snapshot['tools'])->not->toBeNull()
            ->and($snapshot['tools'])->toHaveCount(1)
            ->and($snapshot['tools'][0]['function']['name'])->toBe('SearchKnowledge')
            ->and($snapshot['tools'][0]['type'])->toBe('function');
    }

    public function test_snapshot_includes_multiple_tool_definitions_from_pivot(): void
    {
        $tenant = $this->createTenant();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);

        $tool1 = $this->createTool($tenantId, 'SearchKnowledge', isActive: true);
        $tool2 = $this->createTool($tenantId, 'SendMessage', isActive: true);

        $this->attachTool($agent, $tool1);
        $this->attachTool($agent, $tool2);

        $snapshot = $this->resolver->resolve($tenantId, $agent, '');

        expect($snapshot['tools'])->toHaveCount(2);

        $toolNames = collect($snapshot['tools'])
            ->map(fn (array $def): string => $def['function']['name'])
            ->all();

        expect($toolNames)->toContain('SearchKnowledge', 'SendMessage');
    }

    // =========================================================================
    // resolveTools — tool APENAS em metadata.tool_names NÃO aparece
    // =========================================================================

    public function test_snapshot_does_not_include_tool_only_in_metadata_tool_names(): void
    {
        $tenant = $this->createTenant();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);

        // metadata.tool_names com tool, mas SEM vinculo na pivot
        $agent->update([
            'metadata' => ['tool_names' => ['SearchKnowledge', 'SendMessage']],
        ]);

        $snapshot = $this->resolver->resolve($tenantId, $agent, '');

        // Sem tools na pivot → tools deve ser null
        expect($snapshot['tools'])->toBeNull();
    }

    // =========================================================================
    // resolveTools — agente sem tools retorna null
    // =========================================================================

    public function test_snapshot_tools_is_null_when_agent_has_no_tools(): void
    {
        $tenant = $this->createTenant();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);

        // Nenhuma tool vinculada
        $snapshot = $this->resolver->resolve($tenantId, $agent, '');

        expect($snapshot['tools'])->toBeNull();
    }

    public function test_snapshot_tools_is_null_when_all_linked_tools_are_inactive(): void
    {
        $tenant = $this->createTenant();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);

        $tool = $this->createTool($tenantId, 'SearchKnowledge', isActive: false);
        $this->attachTool($agent, $tool);

        $snapshot = $this->resolver->resolve($tenantId, $agent, '');

        expect($snapshot['tools'])->toBeNull();
    }

    // =========================================================================
    // resolve — estrutura completa do snapshot
    // =========================================================================

    public function test_resolve_returns_complete_snapshot_structure(): void
    {
        $tenant = $this->createTenant();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);

        $tool = $this->createTool($tenantId, 'SearchKnowledge', isActive: true);
        $this->attachTool($agent, $tool);

        $snapshot = $this->resolver->resolve($tenantId, $agent, '');

        expect($snapshot)
            ->toHaveKeys(['prompt', 'context', 'tools', 'hydrated_at'])
            ->and($snapshot['hydrated_at'])->not->toBeEmpty()
            ->and($snapshot['tools'])->not->toBeNull();
    }

    // =========================================================================
    // Isolamento multi-tenant
    // =========================================================================

    public function test_tenant_isolation_tools_do_not_leak_between_tenants(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $tenantAId = (string) $tenantA->id;
        $tenantBId = (string) $tenantB->id;

        $agentA = $this->createAgent($tenantAId, 'Agent A');
        $agentB = $this->createAgent($tenantBId, 'Agent B');

        // Tool com mesmo nome em tenants diferentes
        $toolA = $this->createTool($tenantAId, 'SearchKnowledge');
        $toolB = $this->createTool($tenantBId, 'SearchKnowledge');

        $this->attachTool($agentA, $toolA);
        $this->attachTool($agentB, $toolB);

        $snapshotA = $this->resolver->resolve($tenantAId, $agentA, '');
        $snapshotB = $this->resolver->resolve($tenantBId, $agentB, '');

        // Ambos devem ter 1 tool (cada um vê apenas a sua)
        expect($snapshotA['tools'])->toHaveCount(1)
            ->and($snapshotB['tools'])->toHaveCount(1);

        // Nenhum snapshot deve ter 2 tools
        expect($snapshotA['tools'])->not->toHaveCount(2)
            ->and($snapshotB['tools'])->not->toHaveCount(2);
    }

    // =========================================================================
    // Tolerância a falhas
    // =========================================================================

    public function test_resolve_tools_returns_null_on_service_failure(): void
    {
        $tenant = $this->createTenant();
        $tenantId = (string) $tenant->id;

        $this->createAgent($tenantId);

        // Agent inválido (ID inexistente) → service retorna array vazio
        $invalidAgent = $this->createAgent($tenantId, 'Invalid');
        $invalidAgent->delete();

        $snapshot = $this->resolver->resolve($tenantId, $invalidAgent, '');

        // Agent deletado → não tem tools → null
        expect($snapshot['tools'])->toBeNull();
    }
}
