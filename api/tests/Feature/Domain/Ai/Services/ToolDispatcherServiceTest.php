<?php

declare(strict_types=1);

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotTool;
use Domain\Ai\Services\AiAgentToolPermissionService;
use Domain\Ai\Services\AiPermissionMatrixService;
use Domain\Ai\Services\ToolDispatcherService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->permissionMatrixService = new AiPermissionMatrixService;
    $this->agentToolPermissionService = new AiAgentToolPermissionService;
    $this->service = new ToolDispatcherService(
        $this->agentToolPermissionService,
        $this->permissionMatrixService,
    );
});

/**
 * Helper para vincular tool ao agent na pivot ai_agent_tools.
 */
function attachToolToAgent(AiAgent $agent, AiAutopilotTool $tool): void
{
    DB::table('ai_agent_tools')->insert([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => (string) $agent->tenant_id,
        'agent_id' => (string) $agent->id,
        'tool_id' => (string) $tool->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

describe('ToolDispatcherService::getToolDefinitions', function (): void {
    it('returns agent-specific tools when agentId is provided', function (): void {
        $agent = AiAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $tool1 = AiAutopilotTool::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'send_message',
            'is_active' => true,
        ]);

        $tool2 = AiAutopilotTool::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'update_lead_score',
            'is_active' => true,
        ]);

        // Link only send_message to the agent
        attachToolToAgent($agent, $tool1);

        $definitions = $this->service->getToolDefinitions(
            (string) $this->tenant->id,
            (string) $agent->id,
        );

        $toolNames = array_map(
            static fn (array $definition): string => (string) data_get($definition, 'function.name', ''),
            $definitions,
        );

        expect($toolNames)
            ->toContain('send_message')
            ->not->toContain('update_lead_score');
    });

    it('returns all active tools when agentId is not provided (legacy fallback)', function (): void {
        $definitions = $this->service->getToolDefinitions((string) $this->tenant->id);

        $toolNames = array_map(
            static fn (array $definition): string => (string) data_get($definition, 'function.name', ''),
            $definitions,
        );

        // Legacy fallback returns all tools from the permission matrix
        expect($toolNames)->toContain('send_message');
    });

    it('respects selected tools filter', function (): void {
        $definitions = $this->service->getToolDefinitions(
            (string) $this->tenant->id,
            null,
            ['send_message'],
        );

        $toolNames = array_map(
            static fn (array $definition): string => (string) data_get($definition, 'function.name', ''),
            $definitions,
        );

        expect($toolNames)
            ->toContain('send_message')
            ->not->toContain('update_lead_score');
    });

    it('selected tools filter takes precedence over agentId', function (): void {
        $agent = AiAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $tool = AiAutopilotTool::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'send_message',
            'is_active' => true,
        ]);

        attachToolToAgent($agent, $tool);

        // Even though agent only has send_message, selectedTools overrides
        $definitions = $this->service->getToolDefinitions(
            (string) $this->tenant->id,
            (string) $agent->id,
            ['create_note'],
        );

        $toolNames = array_map(
            static fn (array $definition): string => (string) data_get($definition, 'function.name', ''),
            $definitions,
        );

        expect($toolNames)
            ->toContain('create_note')
            ->not->toContain('send_message');
    });
});

describe('ToolDispatcherService::dispatch', function (): void {
    it('permits dispatch of tool linked to agent', function (): void {
        $agent = AiAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $tool = AiAutopilotTool::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'send_message',
            'is_active' => true,
        ]);

        attachToolToAgent($agent, $tool);

        $result = $this->service->dispatch(
            'send_message',
            ['message' => 'Hello'],
            [
                'tenant_id' => (string) $this->tenant->id,
                'agent_id' => (string) $agent->id,
            ],
        );

        // Authorization passes (tool is linked). Handler may fail on business logic
        // (e.g. ticket not found) but should NOT fail with authorization errors.
        expect($result->message)->not->toContain('not assigned')
            ->and($result->message)->not->toContain('Agent context')
            ->and($result->message)->not->toContain('agent_tools_not_configured');
    });

    it('blocks tool that exists but is not linked to agent', function (): void {
        $agent = AiAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Link a different tool so agent has tools (passes first check)
        $otherTool = AiAutopilotTool::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'create_note',
            'is_active' => true,
        ]);
        attachToolToAgent($agent, $otherTool);

        // Create tool but do NOT link to agent
        AiAutopilotTool::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'send_message',
            'is_active' => true,
        ]);

        $result = $this->service->dispatch(
            'send_message',
            [],
            [
                'tenant_id' => (string) $this->tenant->id,
                'agent_id' => (string) $agent->id,
            ],
        );

        expect($result->success)->toBeFalse()
            ->and($result->data['reason'])->toBe('tool_not_assigned_to_agent');
    });

    it('blocks execution without agent_id in context', function (): void {
        $result = $this->service->dispatch(
            'send_message',
            [],
            [
                'tenant_id' => (string) $this->tenant->id,
            ],
        );

        expect($result->success)->toBeFalse()
            ->and($result->message)->toContain('Agent context not informed');
    });

    it('blocks agent without tools with reason agent_tools_not_configured', function (): void {
        $agent = AiAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Agent exists but has no tools linked

        $result = $this->service->dispatch(
            'send_message',
            [],
            [
                'tenant_id' => (string) $this->tenant->id,
                'agent_id' => (string) $agent->id,
            ],
        );

        expect($result->success)->toBeFalse()
            ->and($result->data['reason'])->toBe('agent_tools_not_configured');
    });

    it('blocks tool for wrong tenant', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        $agent = AiAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $tool = AiAutopilotTool::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'send_message',
            'is_active' => true,
        ]);

        attachToolToAgent($agent, $tool);

        // Try to use a different tenant ID — agent has no tools in otherTenant's context
        $result = $this->service->dispatch(
            'send_message',
            [],
            [
                'tenant_id' => (string) $otherTenant->id,
                'agent_id' => (string) $agent->id,
            ],
        );

        expect($result->success)->toBeFalse()
            ->and($result->data['reason'])->toBe('agent_tools_not_configured');
    });
});

describe('ToolDispatcherService::getCatalog', function (): void {
    it('returns all active tools for tenant without role filtering', function (): void {
        AiAutopilotTool::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'send_message',
            'is_active' => true,
        ]);

        AiAutopilotTool::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'update_lead_score',
            'is_active' => true,
        ]);

        // Inactive tool should not appear
        AiAutopilotTool::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'some_inactive_tool',
            'is_active' => false,
        ]);

        $catalog = $this->service->getCatalog((string) $this->tenant->id);

        $toolNames = array_map(
            static fn (array $item): string => $item['name'],
            $catalog,
        );

        expect($toolNames)
            ->toContain('send_message')
            ->toContain('update_lead_score')
            ->not->toContain('some_inactive_tool');
    });

    it('requires tenantId parameter', function (): void {
        $catalog = $this->service->getCatalog((string) $this->tenant->id);

        expect($catalog)->toBeArray();
    });
});
