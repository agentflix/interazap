<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotTool;
use Domain\Ai\Services\AiAgentToolPermissionService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Testes do serviço AiAgentToolPermissionService.
 *
 * Cobre leitura, verificação, sincronização e isolamento multi-tenant.
 *
 * @group ai
 * @group ai-permissions
 */
class AiAgentToolPermissionServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AiAgentToolPermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AiAgentToolPermissionService;
    }

    /**
     * Helper para criar um agent.
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
     * Helper para criar uma tool.
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
     * Helper para vincular tool ao agent na pivot.
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
    // toolNamesForAgent
    // =========================================================================

    public function test_tool_names_for_agent_returns_active_tool_names(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);

        $tool1 = $this->createTool($tenantId, 'SearchKnowledge');
        $tool2 = $this->createTool($tenantId, 'SendMessage');

        $this->attachTool($agent, $tool1);
        $this->attachTool($agent, $tool2);

        $result = $this->service->toolNamesForAgent($tenantId, (string) $agent->id);

        expect($result)
            ->toHaveCount(2)
            ->toContain('SearchKnowledge', 'SendMessage');
    }

    public function test_tool_names_for_agent_excludes_inactive_tools(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);

        $activeTool = $this->createTool($tenantId, 'ActiveTool', isActive: true);
        $inactiveTool = $this->createTool($tenantId, 'InactiveTool', isActive: false);

        $this->attachTool($agent, $activeTool);
        $this->attachTool($agent, $inactiveTool);

        $result = $this->service->toolNamesForAgent($tenantId, (string) $agent->id);

        expect($result)
            ->toHaveCount(1)
            ->toContain('ActiveTool')
            ->not->toContain('InactiveTool');
    }

    public function test_tool_names_for_agent_returns_empty_when_no_tools_linked(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);

        $result = $this->service->toolNamesForAgent($tenantId, (string) $agent->id);

        expect($result)->toBeEmpty();
    }

    // =========================================================================
    // agentCanUseTool
    // =========================================================================

    public function test_agent_can_use_tool_returns_true_when_linked_and_active(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);
        $tool = $this->createTool($tenantId, 'SearchKnowledge', isActive: true);

        $this->attachTool($agent, $tool);

        $result = $this->service->agentCanUseTool(
            $tenantId,
            (string) $agent->id,
            'SearchKnowledge',
        );

        expect($result)->toBeTrue();
    }

    public function test_agent_can_use_tool_returns_false_when_tool_not_linked(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);
        $this->createTool($tenantId, 'SearchKnowledge', isActive: true);

        $result = $this->service->agentCanUseTool(
            $tenantId,
            (string) $agent->id,
            'SearchKnowledge',
        );

        expect($result)->toBeFalse();
    }

    public function test_agent_can_use_tool_returns_false_when_tool_is_inactive(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);
        $tool = $this->createTool($tenantId, 'SearchKnowledge', isActive: false);

        $this->attachTool($agent, $tool);

        $result = $this->service->agentCanUseTool(
            $tenantId,
            (string) $agent->id,
            'SearchKnowledge',
        );

        expect($result)->toBeFalse();
    }

    public function test_agent_can_use_tool_returns_false_for_nonexistent_tool(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);

        $result = $this->service->agentCanUseTool(
            $tenantId,
            (string) $agent->id,
            'NonExistentTool',
        );

        expect($result)->toBeFalse();
    }

    // =========================================================================
    // syncAgentTools — substitui permissões antigas pelas novas
    // =========================================================================

    public function test_sync_agent_tools_replaces_old_permissions_with_new(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);

        $oldTool1 = $this->createTool($tenantId, 'OldTool1');
        $oldTool2 = $this->createTool($tenantId, 'OldTool2');
        $this->createTool($tenantId, 'NewTool1');
        $this->createTool($tenantId, 'NewTool2');

        // Vincula tools antigas
        $this->attachTool($agent, $oldTool1);
        $this->attachTool($agent, $oldTool2);

        $before = $this->service->toolNamesForAgent($tenantId, (string) $agent->id);
        expect($before)->toHaveCount(2)
            ->toContain('OldTool1', 'OldTool2');

        // Sincroniza com novas tools
        $this->service->syncAgentTools(
            $tenantId,
            (string) $agent->id,
            ['NewTool1', 'NewTool2'],
        );

        $after = $this->service->toolNamesForAgent($tenantId, (string) $agent->id);

        expect($after)
            ->toHaveCount(2)
            ->toContain('NewTool1', 'NewTool2')
            ->not->toContain('OldTool1', 'OldTool2');
    }

    public function test_sync_agent_tools_ignores_nonexistent_tool_names(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);
        $this->createTool($tenantId, 'ValidTool');

        $this->service->syncAgentTools(
            $tenantId,
            (string) $agent->id,
            ['ValidTool', 'DoesNotExist1', 'DoesNotExist2'],
        );

        $result = $this->service->toolNamesForAgent($tenantId, (string) $agent->id);

        expect($result)
            ->toHaveCount(1)
            ->toContain('ValidTool')
            ->not->toContain('DoesNotExist1', 'DoesNotExist2');
    }

    public function test_sync_agent_tools_ignores_inactive_tool_names(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);
        $this->createTool($tenantId, 'ActiveTool', isActive: true);
        $this->createTool($tenantId, 'InactiveTool', isActive: false);

        $this->service->syncAgentTools(
            $tenantId,
            (string) $agent->id,
            ['ActiveTool', 'InactiveTool'],
        );

        $result = $this->service->toolNamesForAgent($tenantId, (string) $agent->id);

        expect($result)
            ->toHaveCount(1)
            ->toContain('ActiveTool')
            ->not->toContain('InactiveTool');
    }

    public function test_sync_agent_tools_clears_all_when_empty_array(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = $this->createAgent($tenantId);
        $tool1 = $this->createTool($tenantId, 'Tool1');
        $tool2 = $this->createTool($tenantId, 'Tool2');

        $this->attachTool($agent, $tool1);
        $this->attachTool($agent, $tool2);

        $this->service->syncAgentTools($tenantId, (string) $agent->id, []);

        $result = $this->service->toolNamesForAgent($tenantId, (string) $agent->id);

        expect($result)->toBeEmpty();
    }

    // =========================================================================
    // Isolamento multi-tenant
    // =========================================================================

    public function test_tenant_isolation_tool_names_for_agent(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        $tenantAId = (string) $tenantA->id;
        $tenantBId = (string) $tenantB->id;

        $agentA = $this->createAgent($tenantAId, 'Agent A');
        $agentB = $this->createAgent($tenantBId, 'Agent B');

        $toolA = $this->createTool($tenantAId, 'ToolA');
        $toolB = $this->createTool($tenantBId, 'ToolB');

        $this->attachTool($agentA, $toolA);
        $this->attachTool($agentB, $toolB);

        // Agent A deve ver apenas ToolA
        $resultA = $this->service->toolNamesForAgent($tenantAId, (string) $agentA->id);
        expect($resultA)
            ->toHaveCount(1)
            ->toContain('ToolA')
            ->not->toContain('ToolB');

        // Agent B deve ver apenas ToolB
        $resultB = $this->service->toolNamesForAgent($tenantBId, (string) $agentB->id);
        expect($resultB)
            ->toHaveCount(1)
            ->toContain('ToolB')
            ->not->toContain('ToolA');
    }

    public function test_tenant_isolation_agent_can_use_tool(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        $tenantAId = (string) $tenantA->id;
        $tenantBId = (string) $tenantB->id;

        $agentA = $this->createAgent($tenantAId, 'Agent A');
        $agentB = $this->createAgent($tenantBId, 'Agent B');

        $toolA = $this->createTool($tenantAId, 'SharedName');
        $toolB = $this->createTool($tenantBId, 'SharedName');

        $this->attachTool($agentA, $toolA);
        $this->attachTool($agentB, $toolB);

        // Agent A pode usar a tool do tenant A
        expect($this->service->agentCanUseTool($tenantAId, (string) $agentA->id, 'SharedName'))
            ->toBeTrue();

        // Agent A NÃO pode usar a tool do tenant B (mesmo nome)
        expect($this->service->agentCanUseTool($tenantBId, (string) $agentA->id, 'SharedName'))
            ->toBeFalse();

        // Agent B pode usar a tool do tenant B
        expect($this->service->agentCanUseTool($tenantBId, (string) $agentB->id, 'SharedName'))
            ->toBeTrue();
    }

    public function test_tenant_isolation_sync_does_not_affect_other_tenant(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        $tenantAId = (string) $tenantA->id;
        $tenantBId = (string) $tenantB->id;

        $agentA = $this->createAgent($tenantAId, 'Agent A');
        $agentB = $this->createAgent($tenantBId, 'Agent B');

        $toolA1 = $this->createTool($tenantAId, 'ToolA1');
        $this->createTool($tenantAId, 'ToolA2');
        $toolB1 = $this->createTool($tenantBId, 'ToolB1');

        $this->attachTool($agentA, $toolA1);
        $this->attachTool($agentB, $toolB1);

        // Sincroniza agent A com ToolA2
        $this->service->syncAgentTools(
            $tenantAId,
            (string) $agentA->id,
            ['ToolA2'],
        );

        // Agent A agora tem apenas ToolA2
        $resultA = $this->service->toolNamesForAgent($tenantAId, (string) $agentA->id);
        expect($resultA)
            ->toHaveCount(1)
            ->toContain('ToolA2')
            ->not->toContain('ToolA1');

        // Agent B continua com ToolB1 intacto
        $resultB = $this->service->toolNamesForAgent($tenantBId, (string) $agentB->id);
        expect($resultB)
            ->toHaveCount(1)
            ->toContain('ToolB1');
    }

    public function test_tenant_isolation_sync_cannot_link_tool_from_different_tenant(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        $tenantAId = (string) $tenantA->id;
        $tenantBId = (string) $tenantB->id;

        $agentA = $this->createAgent($tenantAId, 'Agent A');

        $this->createTool($tenantAId, 'ToolA');
        $this->createTool($tenantBId, 'ToolB');

        // Tenta sincronizar agent A com tools de ambos os tenants
        $this->service->syncAgentTools(
            $tenantAId,
            (string) $agentA->id,
            ['ToolA', 'ToolB'],
        );

        $result = $this->service->toolNamesForAgent($tenantAId, (string) $agentA->id);

        // Apenas ToolA (do tenant correto) deve estar vinculada
        expect($result)
            ->toHaveCount(1)
            ->toContain('ToolA')
            ->not->toContain('ToolB');
    }
}
