<?php

declare(strict_types=1);

use Domain\Ai\Enums\AiToolEnum;
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

describe('ToolDispatcherService dispatch authorization', function (): void {
    it('blocks tools not assigned to agent via database', function (): void {
        $agent = AiAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Agent has no tools linked
        $result = $this->service->dispatch(
            AiToolEnum::UPDATE_LEAD_SCORE,
            [],
            [
                'tenant_id' => (string) $this->tenant->id,
                'agent_id' => (string) $agent->id,
            ],
        );

        expect($result->success)->toBeFalse()
            ->and($result->data['reason'] ?? '')->toBe('agent_tools_not_configured');
    });

    it('blocks tool when agent has different tools linked', function (): void {
        $agent = AiAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Link only send_message, not update_lead_score
        $tool = AiAutopilotTool::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => AiToolEnum::SEND_MESSAGE,
            'is_active' => true,
        ]);

        DB::table('ai_agent_tools')->insert([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $agent->tenant_id,
            'agent_id' => (string) $agent->id,
            'tool_id' => (string) $tool->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->dispatch(
            AiToolEnum::UPDATE_LEAD_SCORE,
            [],
            [
                'tenant_id' => (string) $this->tenant->id,
                'agent_id' => (string) $agent->id,
            ],
        );

        expect($result->success)->toBeFalse()
            ->and($result->data['reason'] ?? '')->toBe('tool_not_assigned_to_agent');
    });
});
