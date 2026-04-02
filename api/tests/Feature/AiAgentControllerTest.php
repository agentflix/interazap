<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Models\AiAgent;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiAgentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function authenticateWithAgentPermission(): AuthUser
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'ai.autopilots.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user->givePermissionTo($permission);
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    public function test_agent_crud_and_toggle_endpoints(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'ai.autopilots.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user->givePermissionTo($permission);
        $this->actingAs($user, 'sanctum');

        $created = $this->postJson('/api/ai/agents', [
            'name' => 'Sales Assistant',
            'type' => 'sales',
            'role' => 'sales_qualifier',
            'model_id' => 'gpt-4o-mini',
            'system_prompt' => 'You are a sales assistant',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ])->assertCreated()->json('data');

        $agentId = $created['id'];

        $this->getJson('/api/ai/agents')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $agentId,
                'name' => 'Sales Assistant',
            ]);

        $this->getJson('/api/ai/agents/'.$agentId)
            ->assertOk()
            ->assertJsonPath('data.id', $agentId);

        $this->putJson('/api/ai/agents/'.$agentId, [
            'name' => 'Sales Assistant Updated',
            'temperature' => 0.5,
        ])->assertOk();

        $this->assertDatabaseHas('ai_agents', [
            'id' => $agentId,
            'name' => 'Sales Assistant Updated',
        ]);

        $this->patchJson('/api/ai/agents/'.$agentId.'/toggle')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson('/api/ai/agents/'.$agentId)
            ->assertNoContent();
    }

    public function test_agent_routes_are_tenant_isolated(): void
    {
        /** @var AuthUser $tenantAUser */
        $tenantAUser = AuthUser::factory()->create();
        /** @var AuthUser $tenantBUser */
        $tenantBUser = AuthUser::factory()->create();
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'ai.autopilots.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $tenantAUser->givePermissionTo($permission);
        $tenantBUser->givePermissionTo($permission);

        $agent = AiAgent::query()->create([
            'tenant_id' => $tenantBUser->tenant_id,
            'name' => 'Tenant B Agent',
            'type' => 'support',
            'role' => 'support_l1',
            'model_id' => 'gpt-4o-mini',
            'system_prompt' => null,
            'max_tokens' => 512,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $this->actingAs($tenantAUser, 'sanctum');

        $this->getJson('/api/ai/agents/'.$agent->id)
            ->assertNotFound();
    }

    public function test_only_one_general_agent_allowed_per_tenant(): void
    {
        $this->authenticateWithAgentPermission();

        // Create the first general agent (should succeed)
        $this->postJson('/api/ai/agents', [
            'name' => 'First General',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'is_active' => true,
        ])->assertCreated();

        // Attempt to create a second general agent (should fail with validation error)
        $this->postJson('/api/ai/agents', [
            'name' => 'Second General',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'is_active' => true,
        ])->assertJsonValidationErrors(['type']);
    }

    public function test_general_agent_limit_is_tenant_isolated(): void
    {
        /** @var AuthUser $tenantAUser */
        $tenantAUser = AuthUser::factory()->create();
        /** @var AuthUser $tenantBUser */
        $tenantBUser = AuthUser::factory()->create();
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'ai.autopilots.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $tenantAUser->givePermissionTo($permission);
        $tenantBUser->givePermissionTo($permission);

        // Tenant A creates a general agent
        AiAgent::query()->create([
            'tenant_id' => $tenantAUser->tenant_id,
            'name' => 'Tenant A General',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.7,
            'is_active' => true,
        ]);

        $this->actingAs($tenantBUser, 'sanctum');

        // Tenant B should be able to create a general agent as well
        $this->postJson('/api/ai/agents', [
            'name' => 'Tenant B General',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'is_active' => true,
        ])->assertCreated();
    }

    public function test_agent_files_endpoints_support_upsert_and_fetch(): void
    {
        $user = $this->authenticateWithAgentPermission();

        $agent = AiAgent::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Agent Files',
            'type' => 'support',
            'role' => 'support_l1',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.5,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $this->putJson('/api/ai/agents/'.$agent->id.'/files/agents.md', [
            'content' => '# AGENTS',
        ])->assertOk();

        $this->getJson('/api/ai/agents/'.$agent->id.'/files')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'agents.md');

        $this->getJson('/api/ai/agents/'.$agent->id.'/files/agents.md')
            ->assertOk()
            ->assertJsonPath('data.content', '# AGENTS');
    }

    public function test_agent_tools_update_persists_pivot_and_metadata(): void
    {
        $user = $this->authenticateWithAgentPermission();

        $agent = AiAgent::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Agent Tools',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.5,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $updateResponse = $this->putJson('/api/ai/agents/'.$agent->id.'/tools', [
            'tool_names' => ['send_message'],
        ])->assertOk();

        $updateResponse
            ->assertJsonPath('data.0.tool_id', 'send_message')
            ->assertJsonPath('data.0.tool_name', 'send_message');

        $agent->refresh();
        $metadata = is_array($agent->metadata) ? $agent->metadata : [];
        $this->assertSame(['send_message'], $metadata['tool_names'] ?? []);

        $this->getJson('/api/ai/agents/'.$agent->id.'/tools')
            ->assertOk()
            ->assertJsonPath('data.0.tool_id', 'send_message')
            ->assertJsonPath('data.0.tool_name', 'send_message');
    }

    public function test_agent_triggers_crud_endpoints(): void
    {
        $user = $this->authenticateWithAgentPermission();

        $agent = AiAgent::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Agent Trigger',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.5,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $trigger = $this->postJson('/api/ai/agents/'.$agent->id.'/triggers', [
            'type' => 'cron',
            'config' => ['expression' => '* * * * *'],
            'status' => 'active',
        ])->assertCreated()->json('data');

        $this->putJson('/api/ai/agents/'.$agent->id.'/triggers/'.$trigger['id'], [
            'type' => 'cron',
            'config' => ['expression' => '*/5 * * * *'],
            'status' => 'inactive',
        ])->assertOk();

        $this->getJson('/api/ai/agents/'.$agent->id.'/triggers')
            ->assertOk()
            ->assertJsonFragment(['status' => 'inactive']);

        $this->deleteJson('/api/ai/agents/'.$agent->id.'/triggers/'.$trigger['id'])
            ->assertNoContent();
    }

    public function test_agent_channels_crud_endpoints(): void
    {
        $user = $this->authenticateWithAgentPermission();

        $agent = AiAgent::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Agent Channel',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.5,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $channel = $this->postJson('/api/ai/agents/'.$agent->id.'/channels', [
            'channel' => 'whatsapp',
            'external_ref' => 'instance-1',
            'is_active' => true,
        ])->assertCreated()->json('data');

        $this->putJson('/api/ai/agents/'.$agent->id.'/channels/'.$channel['id'], [
            'channel' => 'whatsapp',
            'external_ref' => 'instance-2',
            'is_active' => false,
        ])->assertOk();

        $this->getJson('/api/ai/agents/'.$agent->id.'/channels')
            ->assertOk()
            ->assertJsonFragment(['external_ref' => 'instance-2']);

        $this->deleteJson('/api/ai/agents/'.$agent->id.'/channels/'.$channel['id'])
            ->assertNoContent();
    }

    public function test_agent_voice_update_endpoint(): void
    {
        $user = $this->authenticateWithAgentPermission();

        $agent = AiAgent::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Agent Voice',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.5,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $this->putJson('/api/ai/agents/'.$agent->id.'/voice', [
            'voice_response_mode' => 'audio',
            'stt_model' => 'whisper-1',
            'stt_language' => 'pt',
            'tts_model' => 'gpt-4o-mini-tts',
            'tts_voice' => 'alloy',
            'tts_speed' => 1.2,
        ])->assertOk();

        $this->getJson('/api/ai/agents/'.$agent->id.'/voice')
            ->assertOk()
            ->assertJsonPath('data.voice_response_mode', 'audio')
            ->assertJsonPath('data.stt_model', 'whisper-1');
    }

    public function test_agent_voice_update_accepts_legacy_mode_values(): void
    {
        $user = $this->authenticateWithAgentPermission();

        $agent = AiAgent::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Agent Voice Legacy',
            'type' => 'general',
            'role' => 'general',
            'model_id' => 'gpt-4o-mini',
            'max_tokens' => 512,
            'temperature' => 0.5,
            'top_p' => 1.0,
            'is_active' => true,
        ]);

        $this->putJson('/api/ai/agents/'.$agent->id.'/voice', [
            'voice_response_mode' => 'both',
            'stt_model' => 'whisper-1',
            'stt_language' => 'pt-BR',
            'tts_model' => 'tts-1',
            'tts_voice' => 'alloy',
            'tts_speed' => 1.0,
        ])->assertOk();

        $this->getJson('/api/ai/agents/'.$agent->id.'/voice')
            ->assertOk()
            ->assertJsonPath('data.voice_response_mode', 'mixed');
    }
}
