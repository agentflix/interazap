<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Models;

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotTool;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Testes do model AiAgent — relacionamentos e isolamento multi-tenant.
 *
 * @group ai
 * @group ai-agent
 */
class AiAgentTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Helper para vincular tool ao agent na pivot (UUID primary key).
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

    public function test_it_has_tools_relationship(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'Test Agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $tool1 = AiAutopilotTool::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'SearchKnowledgeTool',
            'display_name' => 'Search Knowledge',
            'description' => 'Searches the knowledge base',
            'parameters_schema' => ['type' => 'object'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $tool2 = AiAutopilotTool::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'SendMessageTool',
            'display_name' => 'Send Message',
            'description' => 'Sends a message',
            'parameters_schema' => ['type' => 'object'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->attachTool($agent, $tool1);
        $this->attachTool($agent, $tool2);

        $tools = $agent->tools;

        expect($tools)->toHaveCount(2)
            ->and($tools->pluck('id')->toArray())->toContain($tool1->id, $tool2->id);
    }

    public function test_it_returns_only_linked_tools(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'Test Agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $linkedTool = AiAutopilotTool::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'SearchKnowledgeTool',
            'display_name' => 'Search Knowledge',
            'description' => 'Searches the knowledge base',
            'parameters_schema' => ['type' => 'object'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $unlinkedTool = AiAutopilotTool::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'SendMessageTool',
            'display_name' => 'Send Message',
            'description' => 'Sends a message',
            'parameters_schema' => ['type' => 'object'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->attachTool($agent, $linkedTool);

        $tools = $agent->tools;

        expect($tools)->toHaveCount(1)
            ->and($tools->first()->id)->toBe($linkedTool->id)
            ->and($tools->pluck('id')->toArray())->not->toContain($unlinkedTool->id);
    }

    public function test_tenant_isolation_agent_tools(): void
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

        $toolA = AiAutopilotTool::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantAId,
            'name' => 'ToolA',
            'display_name' => 'Tool A',
            'description' => 'Tool for tenant A',
            'parameters_schema' => ['type' => 'object'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $toolB = AiAutopilotTool::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantBId,
            'name' => 'ToolB',
            'display_name' => 'Tool B',
            'description' => 'Tool for tenant B',
            'parameters_schema' => ['type' => 'object'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->attachTool($agentA, $toolA);
        $this->attachTool($agentB, $toolB);

        // Agent A must NOT see Tool B (different tenant)
        $toolsA = $agentA->tools;
        expect($toolsA)->toHaveCount(1)
            ->and($toolsA->first()->id)->toBe($toolA->id)
            ->and($toolsA->pluck('id')->toArray())->not->toContain($toolB->id);

        // Agent B must NOT see Tool A (different tenant)
        $toolsB = $agentB->tools;
        expect($toolsB)->toHaveCount(1)
            ->and($toolsB->first()->id)->toBe($toolB->id)
            ->and($toolsB->pluck('id')->toArray())->not->toContain($toolA->id);
    }

    public function test_it_has_belongs_to_tenant_trait(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Test Agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        // BelongsToTenant provides tenant() relationship and forTenant() scope
        expect($agent->tenant)->toBeInstanceOf(PlatformTenant::class)
            ->and($agent->tenant->id)->toBe($tenant->id);

        // Verify the trait's scope is available via class traits
        $traits = class_uses_recursive(AiAgent::class);
        expect($traits)->toContain(\Domain\Shared\Concerns\BelongsToTenant::class);
    }

    public function test_tools_relationship_returns_belongs_to_many(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Test Agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        expect($agent->tools())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
    }

    public function test_it_can_detach_tools(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'Test Agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $tool = AiAutopilotTool::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'SearchKnowledgeTool',
            'display_name' => 'Search Knowledge',
            'description' => 'Searches the knowledge base',
            'parameters_schema' => ['type' => 'object'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->attachTool($agent, $tool);
        expect($agent->tools)->toHaveCount(1);

        DB::table('ai_agent_tools')
            ->where('agent_id', $agent->id)
            ->where('tool_id', $tool->id)
            ->delete();

        // Refresh the relationship
        $agent->unsetRelation('tools');
        expect($agent->tools)->toHaveCount(0);
    }

    public function test_it_can_sync_tools(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'Test Agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $tool1 = AiAutopilotTool::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'SearchKnowledgeTool',
            'display_name' => 'Search Knowledge',
            'description' => 'Searches the knowledge base',
            'parameters_schema' => ['type' => 'object'],
            'is_system' => true,
            'is_active' => true,
        ]);

        $tool2 = AiAutopilotTool::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'SendMessageTool',
            'display_name' => 'Send Message',
            'description' => 'Sends a message',
            'parameters_schema' => ['type' => 'object'],
            'is_system' => true,
            'is_active' => true,
        ]);

        // Attach both
        $this->attachTool($agent, $tool1);
        $this->attachTool($agent, $tool2);
        expect($agent->fresh()->tools)->toHaveCount(2);

        // Remove tool1 — only tool2 should remain
        DB::table('ai_agent_tools')
            ->where('agent_id', $agent->id)
            ->where('tool_id', $tool1->id)
            ->delete();

        expect($agent->fresh()->tools)->toHaveCount(1)
            ->and($agent->fresh()->tools->first()->id)->toBe($tool2->id);
    }
}
