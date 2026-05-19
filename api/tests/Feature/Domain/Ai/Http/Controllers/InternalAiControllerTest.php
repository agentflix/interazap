<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Http\Controllers;

use Domain\Ai\Jobs\AiRunExecutionJob;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentDelegation;
use Domain\Ai\Models\AiAutopilotPlaybook;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Models\AiAutopilotTool;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class InternalAiControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.gateway.api_key', 'internal-test-key');
    }

    /**
     * Helper para criar uma tool na base de dados.
     */
    private function createTool(string $tenantId, string $name, bool $isActive = true): AiAutopilotTool
    {
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
     * Helper para vincular tool ao agente na pivot ai_agent_tools.
     */
    private function attachToolToAgent(string $tenantId, string $agentId, AiAutopilotTool $tool): void
    {
        DB::table('ai_agent_tools')->insert([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'tool_id' => (string) $tool->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_context_endpoint_returns_ticket_context(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create([
            'subject' => 'Ticket Subject',
        ]);

        $this->getJson('/api/internal/ai/context/'.$ticket->id.'?tenant_id='.$tenant->id, [
            'X-Internal-Api-Key' => 'internal-test-key',
        ])
            ->assertOk()
            ->assertJsonPath('data.ticket_id', (string) $ticket->id)
            ->assertJsonPath('data.tenant_id', (string) $tenant->id);
    }

    public function test_prompt_endpoint_returns_prompt_payload(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $this->getJson('/api/internal/ai/prompt/'.$tenant->id, [
            'X-Internal-Api-Key' => 'internal-test-key',
        ])
            ->assertOk()
            ->assertJsonPath('data.tenant_id', (string) $tenant->id);
    }

    public function test_tools_endpoint_returns_tool_definitions(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Internal Agent',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        // Vincula tool via pivot (ai_agent_tools)
        $tool = $this->createTool((string) $tenant->id, 'send_message');
        $this->attachToolToAgent((string) $tenant->id, (string) $agent->id, $tool);

        $this->getJson('/api/internal/ai/tools/'.$agent->id.'?tenant_id='.$tenant->id, [
            'X-Internal-Api-Key' => 'internal-test-key',
        ])
            ->assertOk()
            ->assertJsonPath('data.agent_id', (string) $agent->id);
    }

    public function test_tools_endpoint_returns_only_linked_tools_from_pivot(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'Test Agent',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        // Cria duas tools, mas vincula apenas uma ao agente
        $linkedTool = $this->createTool($tenantId, 'send_message');
        $unlinkedTool = $this->createTool($tenantId, 'search_knowledge');

        $this->attachToolToAgent($tenantId, (string) $agent->id, $linkedTool);

        $response = $this->getJson('/api/internal/ai/tools/'.$agent->id, [
            'X-Internal-Api-Key' => 'internal-test-key',
        ])
            ->assertOk();

        $tools = $response->json('data.tools');
        expect($tools)->toHaveCount(1);
        expect($tools[0]['function']['name'])->toBe('send_message');
    }

    public function test_tools_endpoint_returns_empty_when_agent_has_no_tools(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Empty Agent',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/internal/ai/tools/'.$agent->id, [
            'X-Internal-Api-Key' => 'internal-test-key',
        ])
            ->assertOk();

        $tools = $response->json('data.tools');
        expect($tools)->toBeArray()->toHaveCount(0);
    }

    public function test_execute_tool_endpoint_returns_dispatch_result(): void
    {
        $this->postJson('/api/internal/ai/tool/non_existing_tool', [
            'parameters' => [],
            'context' => [
                'tenant_id' => (string) Str::orderedUuid(),
                'agent_id' => (string) Str::orderedUuid(),
            ],
        ], [
            'X-Internal-Api-Key' => 'internal-test-key',
        ])
            ->assertOk()
            ->assertJsonPath('data.success', false);
    }

    public function test_execute_tool_fails_without_agent_id_in_context(): void
    {
        $response = $this->postJson('/api/internal/ai/tool/some_tool', [
            'parameters' => [],
            'context' => [
                'tenant_id' => (string) Str::orderedUuid(),
                'agent_role' => 'general',
            ],
        ], [
            'X-Internal-Api-Key' => 'internal-test-key',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.reason', 'agent_id_missing');
    }

    public function test_execute_tool_blocks_tool_without_pivot_link(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'Test Agent',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        // Vincula uma tool diferente (send_message) mas tenta executar search_knowledge
        $linkedTool = $this->createTool($tenantId, 'send_message');
        $this->attachToolToAgent($tenantId, (string) $agent->id, $linkedTool);

        $response = $this->postJson('/api/internal/ai/tool/search_knowledge', [
            'parameters' => [],
            'context' => [
                'tenant_id' => $tenantId,
                'agent_id' => (string) $agent->id,
            ],
        ], [
            'X-Internal-Api-Key' => 'internal-test-key',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.success', false)
            ->assertJsonPath('data.data.reason', 'tool_not_assigned_to_agent');
    }

    public function test_execute_tool_send_message_dispatches_to_gateway_and_persists_message(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
            'provider' => 'uazapi',
            'webhook_token' => 'uazapi-token-test',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenantId)->create([
            'instance_id' => (string) $instance->id,
            'status' => 'open',
            'phone' => '5511999999999',
        ]);

        // Cria e vincula a tool send_message ao agente
        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => 'AI Agent',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'is_active' => true,
        ]);
        $tool = $this->createTool($tenantId, 'send_message');
        $this->attachToolToAgent($tenantId, (string) $agent->id, $tool);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('sendText')
            ->once()
            ->with('uazapi-token-test', Mockery::on(fn (array $payload): bool => ($payload['number'] ?? null) === '5511999999999'
                && ($payload['text'] ?? null) === 'Mensagem enviada pela IA'))
            ->andReturn(['messageid' => 'uazapi-msg-123']);
        $this->app->instance(ChatGatewayService::class, $gateway);

        $response = $this->postJson('/api/internal/ai/tool/send_message', [
            'parameters' => [
                'ticket_id' => (string) $ticket->id,
                'content' => 'Mensagem enviada pela IA',
                'type' => 'text',
            ],
            'context' => [
                'tenant_id' => $tenantId,
                'agent_id' => (string) $agent->id,
            ],
        ], [
            'X-Internal-Api-Key' => 'internal-test-key',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.message', 'Message sent successfully');

        $messageId = (string) $response->json('data.data.message_id');
        $message = ChatMessage::query()->find($messageId);

        expect($message)->not->toBeNull();
        expect($message?->tenant_id)->toBe($tenantId);
        expect($message?->ticket_id)->toBe((string) $ticket->id);
        expect($message?->direction)->toBe('outgoing');
        expect($message?->source)->toBe('ai');
        expect($message?->status)->toBe('sent');
        expect($message?->external_id)->toBe('uazapi-msg-123');
    }

    public function test_knowledge_search_endpoint_validates_payload(): void
    {
        $this->getJson('/api/internal/ai/knowledge/search', [
            'X-Internal-Api-Key' => 'internal-test-key',
        ])->assertStatus(422);
    }

    public function test_delegate_endpoint_creates_child_run_publishes_stream_and_does_not_enqueue_execution_job(): void
    {
        Queue::fake();

        $tenant = PlatformTenant::factory()->create();
        $playbook = AiAutopilotPlaybook::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $sourceAgent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Source Agent',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $targetAgent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Target Agent',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        // Vincula tool send_message ao target agent via pivot
        $tool = $this->createTool((string) $tenant->id, 'send_message');
        $this->attachToolToAgent((string) $tenant->id, (string) $targetAgent->id, $tool);

        AiAgentDelegation::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'source_agent_id' => (string) $sourceAgent->id,
            'target_agent_id' => (string) $targetAgent->id,
            'max_depth' => 3,
            'is_active' => true,
        ]);

        $parentRun = AiAutopilotRun::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'playbook_id' => (string) $playbook->id,
            'playbook_version' => 1,
            'status' => 'running',
            'delegation_depth' => 0,
            'input_context' => [
                'agent_id' => (string) $sourceAgent->id,
                'ticket_id' => 'ticket-123',
                'body' => 'Summarize ticket and delegate.',
            ],
        ]);

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('xadd')
            ->once()
            ->with('ai.run.request', '*', Mockery::on(fn (array $payload): bool => ($payload['event'] ?? null) === 'ai.run.request'
                && ($payload['tenant_id'] ?? null) === (string) $tenant->id
                && ($payload['agent_id'] ?? null) === (string) $targetAgent->id
                && ($payload['parent_run_id'] ?? null) === (string) $parentRun->id))
            ->andReturn('1-0');

        Redis::shouldReceive('connection')
            ->with('gateway')
            ->andReturn($redisConnection);

        $response = $this->postJson('/api/internal/ai/runs/delegate', [
            'tenant_id' => (string) $tenant->id,
            'parent_run_id' => (string) $parentRun->id,
            'target_agent_id' => (string) $targetAgent->id,
            'delegation_stack' => [(string) $sourceAgent->id],
            'input_context' => [
                'ticket_id' => 'ticket-123',
                'body' => 'Summarize ticket and delegate.',
            ],
        ], [
            'X-Internal-Api-Key' => 'internal-test-key',
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.child_run_id', fn (string $id): bool => $id !== '');

        $childRunId = (string) $response->json('data.child_run_id');
        $childRun = AiAutopilotRun::query()->find($childRunId);

        expect($childRun)->not->toBeNull();
        expect((string) $childRun?->parent_run_id)->toBe((string) $parentRun->id);
        expect((string) $childRun?->status)->toBe('queued');

        Queue::assertNotPushed(AiRunExecutionJob::class);
    }

    public function test_delegate_endpoint_rejects_circular_stack(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $targetAgentId = (string) Str::orderedUuid();

        $this->postJson('/api/internal/ai/runs/delegate', [
            'tenant_id' => (string) $tenant->id,
            'parent_run_id' => (string) Str::orderedUuid(),
            'target_agent_id' => $targetAgentId,
            'delegation_stack' => [$targetAgentId],
            'input_context' => [
                'ticket_id' => 'ticket-abc',
            ],
        ], [
            'X-Internal-Api-Key' => 'internal-test-key',
        ])
            ->assertStatus(422)
            ->assertJson([
                'error' => 'circular_delegation',
            ]);
    }

    // TASK-045.2
    public function test_delegate_endpoint_accepts_agent_name_instead_of_uuid(): void
    {
        Queue::fake();

        $tenant = PlatformTenant::factory()->create();
        $playbook = AiAutopilotPlaybook::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $sourceAgent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Source Agent',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $targetAgent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Agente Financeiro',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        AiAgentDelegation::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'source_agent_id' => (string) $sourceAgent->id,
            'target_agent_id' => (string) $targetAgent->id,
            'max_depth' => 3,
            'is_active' => true,
        ]);

        $parentRun = AiAutopilotRun::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'playbook_id' => (string) $playbook->id,
            'playbook_version' => 1,
            'status' => 'running',
            'delegation_depth' => 0,
            'input_context' => [
                'agent_id' => (string) $sourceAgent->id,
                'ticket_id' => 'ticket-fin-001',
                'body' => 'Resolver questão financeira.',
            ],
        ]);

        $targetAgentUuid = (string) $targetAgent->id;

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('xadd')
            ->once()
            ->with('ai.run.request', '*', Mockery::on(fn (array $payload): bool => ($payload['event'] ?? null) === 'ai.run.request'
                && ($payload['agent_id'] ?? null) === $targetAgentUuid))
            ->andReturn('1-0');

        Redis::shouldReceive('connection')
            ->with('gateway')
            ->andReturn($redisConnection);

        $response = $this->postJson('/api/internal/ai/runs/delegate', [
            'tenant_id' => (string) $tenant->id,
            'parent_run_id' => (string) $parentRun->id,
            'target_agent_id' => 'Agente Financeiro',
            'delegation_stack' => [(string) $sourceAgent->id],
            'input_context' => [
                'ticket_id' => 'ticket-fin-001',
                'body' => 'Resolver questão financeira.',
            ],
        ], [
            'X-Internal-Api-Key' => 'internal-test-key',
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued');

        $childRunId = (string) $response->json('data.child_run_id');
        expect($childRunId)->not->toBe('');

        $childRun = AiAutopilotRun::query()->find($childRunId);
        expect($childRun)->not->toBeNull();
        expect((string) $childRun?->parent_run_id)->toBe((string) $parentRun->id);

        Queue::assertNotPushed(AiRunExecutionJob::class);
    }

    // TASK-045.3
    public function test_delegate_endpoint_returns_422_for_unknown_agent_name(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $playbook = AiAutopilotPlaybook::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $sourceAgent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Source Agent',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $parentRun = AiAutopilotRun::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'playbook_id' => (string) $playbook->id,
            'playbook_version' => 1,
            'status' => 'running',
            'delegation_depth' => 0,
            'input_context' => [
                'agent_id' => (string) $sourceAgent->id,
                'ticket_id' => 'ticket-xyz',
                'body' => 'Algum contexto.',
            ],
        ]);

        $response = $this->postJson('/api/internal/ai/runs/delegate', [
            'tenant_id' => (string) $tenant->id,
            'parent_run_id' => (string) $parentRun->id,
            'target_agent_id' => 'Agente Que Nao Existe',
            'delegation_stack' => [(string) $sourceAgent->id],
            'input_context' => [
                'ticket_id' => 'ticket-xyz',
            ],
        ], [
            'X-Internal-Api-Key' => 'internal-test-key',
        ]);

        $response->assertStatus(422);

        $message = (string) ($response->json('message') ?? $response->json('error') ?? '');
        expect($message)->toContain('Agente Que Nao Existe');
    }

    // TASK-045.5
    public function test_delegate_endpoint_returns_422_with_reason_when_no_rule_exists(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $playbook = AiAutopilotPlaybook::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $sourceAgent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Source Agent No Rule',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $targetAgent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Target Agent No Rule',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $parentRun = AiAutopilotRun::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'playbook_id' => (string) $playbook->id,
            'playbook_version' => 1,
            'status' => 'running',
            'delegation_depth' => 0,
            'input_context' => [
                'agent_id' => (string) $sourceAgent->id,
                'ticket_id' => 'ticket-norule',
                'body' => 'Contexto sem regra.',
            ],
        ]);

        $response = $this->postJson('/api/internal/ai/runs/delegate', [
            'tenant_id' => (string) $tenant->id,
            'parent_run_id' => (string) $parentRun->id,
            'target_agent_id' => (string) $targetAgent->id,
            'delegation_stack' => [(string) $sourceAgent->id],
            'input_context' => [
                'ticket_id' => 'ticket-norule',
            ],
        ], [
            'X-Internal-Api-Key' => 'internal-test-key',
        ]);

        $response->assertStatus(422);

        $message = (string) ($response->json('message') ?? '');
        expect(
            str_contains($message, (string) $sourceAgent->id) || str_contains($message, (string) $targetAgent->id)
        )->toBeTrue();
    }

    // TASK-045.7
    public function test_available_agents_endpoint_returns_delegatable_agents(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $sourceAgent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Source Agent',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $delegatableAgent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Agente Financeiro',
            'type' => 'general',
            'role' => 'finance',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Agente Sem Regra',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $inactiveAgent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Agente Inativo',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => false,
        ]);

        // Apenas delegatableAgent tem regra ativa
        AiAgentDelegation::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'source_agent_id' => (string) $sourceAgent->id,
            'target_agent_id' => (string) $delegatableAgent->id,
            'max_depth' => 3,
            'is_active' => true,
        ]);

        // inactiveAgent tem regra mas o agente está inativo
        AiAgentDelegation::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'source_agent_id' => (string) $sourceAgent->id,
            'target_agent_id' => (string) $inactiveAgent->id,
            'max_depth' => 3,
            'is_active' => true,
        ]);

        $response = $this->getJson(
            '/api/internal/ai/agents/available?tenant_id='.$tenant->id.'&agent_id='.$sourceAgent->id,
            ['X-Internal-Api-Key' => 'internal-test-key'],
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data.agents');
        $response->assertJsonPath('data.agents.0.id', (string) $delegatableAgent->id);
        $response->assertJsonPath('data.agents.0.name', 'Agente Financeiro');
    }
}
