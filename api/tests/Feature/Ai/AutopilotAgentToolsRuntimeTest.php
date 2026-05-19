<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotTool;
use Domain\Ai\Services\AiAgentToolPermissionService;
use Domain\Ai\Services\AutopilotRunSnapshotResolver;
use Domain\Ai\Services\ToolDispatcherService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Teste integrado que valida o runtime de agent tools na refatoração.
 *
 * Garante que:
 * - Agente sem tools gera falha/bloqueio com reason `agent_tools_not_configured`
 * - Ausência de fallback por `sales_qualifier`, `support_l1` ou `general`
 * - Presença de `agent_tools_not_configured` como reason
 *
 * @group ai
 * @group ai-tools-runtime
 */
final class AutopilotAgentToolsRuntimeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ToolDispatcherService $dispatcher;

    private AutopilotRunSnapshotResolver $snapshotResolver;

    private AiAgentToolPermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = app(ToolDispatcherService::class);
        $this->snapshotResolver = app(AutopilotRunSnapshotResolver::class);
        $this->permissionService = app(AiAgentToolPermissionService::class);
    }

    // =========================================================================
    // Agente sem tools → bloqueio com reason agent_tools_not_configured
    // =========================================================================

    public function test_agent_without_tools_is_blocked_with_correct_reason(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'Agent Without Tools',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        // Dispatch de qualquer tool deve ser bloqueado
        $result = $this->dispatcher->dispatch(
            'send_message',
            [],
            [
                'tenant_id' => $tenantId,
                'agent_id' => (string) $agent->id,
            ],
        );

        expect($result->success)->toBeFalse()
            ->and($result->data['reason'])->toBe('agent_tools_not_configured');
    }

    // =========================================================================
    // Snapshot de run sem tools → tools é null
    // =========================================================================

    public function test_snapshot_returns_null_tools_for_agent_without_tools(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'Empty Agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $snapshot = $this->snapshotResolver->resolve($tenantId, $agent, '');

        expect($snapshot)
            ->toHaveKey('tools')
            ->and($snapshot['tools'])->toBeNull();
    }

    // =========================================================================
    // Sem fallback por role/type — agente do tipo general sem tools não recebe
    // tools automáticas
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\DataProvider('agentTypeProvider')]
    public function test_no_fallback_by_agent_type(string $agentType): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => "{$agentType} Agent",
            'type' => $agentType,
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        // Nenhum tool names retornado para agente sem tools vinculadas
        $toolNames = $this->permissionService->toolNamesForAgent($tenantId, (string) $agent->id);

        expect($toolNames)->toBeEmpty();

        // Snapshot também não deve ter tools
        $snapshot = $this->snapshotResolver->resolve($tenantId, $agent, '');
        expect($snapshot['tools'])->toBeNull();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function agentTypeProvider(): array
    {
        return [
            'sales_qualifier' => ['sales_qualifier'],
            'support_l1' => ['support_l1'],
            'general' => ['general'],
        ];
    }

    // =========================================================================
    // Agente com tools → dispatch permitido (autorização passa)
    // =========================================================================

    public function test_agent_with_tools_passes_authorization(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'Configured Agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $tool = AiAutopilotTool::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'send_message',
            'display_name' => 'Send Message',
            'description' => 'Sends a message',
            'parameters_schema' => ['type' => 'object'],
            'is_system' => false,
            'is_active' => true,
        ]);

        DB::table('ai_agent_tools')->insert([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'agent_id' => (string) $agent->id,
            'tool_id' => (string) $tool->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Authorization should pass — tool is linked
        $result = $this->dispatcher->dispatch(
            'send_message',
            ['message' => 'test'],
            [
                'tenant_id' => $tenantId,
                'agent_id' => (string) $agent->id,
            ],
        );

        // May fail on business logic (no ticket), but NOT on authorization
        expect($result->message)->not->toContain('agent_tools_not_configured')
            ->and($result->message)->not->toContain('not assigned');
    }

    // =========================================================================
    // Isolamento multi-tenant: agente do tenant A não vê tools do tenant B
    // =========================================================================

    public function test_tenant_isolation_agent_a_cannot_see_tools_from_tenant_b(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        $tenantAId = (string) $tenantA->id;
        $tenantBId = (string) $tenantB->id;

        $agentA = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantAId,
            'name' => 'Agent A',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $agentB = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantBId,
            'name' => 'Agent B',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        // Tool only in tenant B
        $toolB = AiAutopilotTool::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantBId,
            'name' => 'send_message',
            'display_name' => 'Send Message',
            'description' => 'Sends a message',
            'parameters_schema' => ['type' => 'object'],
            'is_system' => false,
            'is_active' => true,
        ]);

        DB::table('ai_agent_tools')->insert([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantBId,
            'agent_id' => (string) $agentB->id,
            'tool_id' => (string) $toolB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Agent A (tenant A) trying to dispatch with tenant B's tool
        // Should be blocked because agent A has no tools in tenant A context
        $result = $this->dispatcher->dispatch(
            'send_message',
            [],
            [
                'tenant_id' => $tenantAId,
                'agent_id' => (string) $agentA->id,
            ],
        );

        expect($result->success)->toBeFalse()
            ->and($result->data['reason'])->toBe('agent_tools_not_configured');

        // Agent B (tenant B) can use the tool
        $resultB = $this->dispatcher->dispatch(
            'send_message',
            [],
            [
                'tenant_id' => $tenantBId,
                'agent_id' => (string) $agentB->id,
            ],
        );

        // Authorization passes (tool is linked), may fail on business logic
        expect($resultB->message)->not->toContain('agent_tools_not_configured');
    }
}
