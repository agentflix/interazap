<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\Ai\Listeners\AutopilotRunDispatcherListener;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentFile;
use Domain\Ai\Models\AiAgentTrigger;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Models\AiPromptMaster;
use Domain\Ai\Models\AiPromptPlan;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Ai\Observers\AiAgentTriggerObserver;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class AutopilotRunDispatcherListenerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_persists_run_and_emits_started_chat_activity_for_inbound_message(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create();
        $plan = PlatformPlan::factory()->create();

        $master = AiPromptMaster::query()->create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'Master Prompt',
            'content' => 'Super prompt content',
            'version' => 1,
            'is_active' => true,
        ]);
        $segment = AiPromptSegment::query()->create([
            'id' => (string) Str::orderedUuid(),
            'master_id' => (string) $master->id,
            'code' => 'TEST_SEGMENT',
            'name' => 'Test Segment',
            'description' => 'Segment for listener test',
            'content' => 'Segment prompt content',
            'is_active' => true,
        ]);
        AiPromptPlan::query()->create([
            'id' => (string) Str::orderedUuid(),
            'plan_id' => (string) $plan->id,
            'content' => 'Plan prompt content',
            'mandatory_rules' => [],
            'token_limit_monthly' => null,
            'allow_overage' => false,
            'overage_price_per_1k' => null,
            'is_active' => true,
        ]);
        $tenant->update([
            'segment_id' => (string) $segment->id,
            'plan_id' => (string) $plan->id,
        ]);

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Primary agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'system_prompt' => 'Agent system prompt content',
            'metadata' => [],
            'is_active' => true,
        ]);

        AiAgentFile::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'agent_id' => (string) $agent->id,
            'slug' => 'z-rules',
            'content' => 'Z rules content',
        ]);
        AiAgentFile::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'agent_id' => (string) $agent->id,
            'slug' => 'a-context',
            'content' => 'A context content',
        ]);
        AiAgentFile::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'agent_id' => (string) $agent->id,
            'slug' => 'm-empty',
            'content' => '   ',
        ]);

        $messageId = (string) Str::orderedUuid();
        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('set')
            ->once()
            ->with(Mockery::type('string'), '1', 'EX', 60, 'NX')
            ->andReturn('OK');
        $redisConnection->shouldReceive('xadd')
            ->once()
            ->with('ai.run.request', '*', Mockery::on(function (array $payload) use ($tenant, $ticket, $agent): bool {
                $filePrompts = json_decode((string) ($payload['agent_file_prompts'] ?? ''), true);

                return $payload['tenant_id'] === (string) $tenant->id
                    && $payload['ticket_id'] === (string) $ticket->id
                    && $payload['agent_id'] === (string) $agent->id
                    && $payload['event'] === 'ai.run.request'
                    && ($payload['super_prompt'] ?? '') === 'Super prompt content'
                    && ($payload['segment_prompt'] ?? '') === 'Segment prompt content'
                    && ($payload['plan_prompt'] ?? '') === 'Plan prompt content'
                    && ($payload['agent_system_prompt'] ?? '') === 'Agent system prompt content'
                    && is_array($filePrompts)
                    && $filePrompts === [
                        "[a-context]\nA context content",
                        "[z-rules]\nZ rules content",
                    ];
            }))
            ->andReturn('1-0');
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::on(function (string $payload) use ($ticket, $tenant, $messageId): bool {
                $decoded = json_decode($payload, true);

                return is_array($decoded)
                    && ($decoded['event'] ?? null) === 'chat.activity'
                    && ($decoded['tenant_id'] ?? null) === (string) $tenant->id
                    && data_get($decoded, 'data.ticketId') === (string) $ticket->id
                    && data_get($decoded, 'data.subevents.0.type') === 'ai.processing.started'
                    && data_get($decoded, 'data.subevents.0.data.message_id') === $messageId;
            }))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->with(config('gateway.redis.connection', 'gateway'))
            ->andReturn($redisConnection);

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => (string) $ticket->id,
                'message_id' => $messageId,
                'body' => 'Hello AI',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: (string) $ticket->id,
        ));

        $run = AiAutopilotRun::query()->where('tenant_id', (string) $tenant->id)->first();

        $this->assertNotNull($run);
        $this->assertSame('queued', $run->status);
        $this->assertSame((string) $ticket->id, data_get($run->input_context, 'ticket_id'));
        $this->assertSame($messageId, data_get($run->input_context, 'message_id'));
        $this->assertSame((string) $agent->id, data_get($run->input_context, 'agent_id'));
    }

    public function test_idempotency_lock_skips_second_dispatch_with_same_message_id(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create();

        // An active agent must exist so the listener would normally proceed.
        AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Primary agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'metadata' => [],
            'is_active' => true,
        ]);

        $messageId = (string) Str::orderedUuid();

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('set')
            ->twice()
            ->with(Mockery::type('string'), '1', 'EX', 60, 'NX')
            ->andReturn('OK', null);
        $redisConnection->shouldReceive('xadd')
            ->once()
            ->with('ai.run.request', '*', Mockery::type('array'))
            ->andReturn('1-0');
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::type('string'))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->with(config('gateway.redis.connection', 'gateway'))
            ->andReturn($redisConnection);

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => (string) $ticket->id,
                'message_id' => $messageId,
                'body' => 'Duplicate message',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: (string) $ticket->id,
        ));

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => (string) $ticket->id,
                'message_id' => $messageId,
                'body' => 'Duplicate message',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: (string) $ticket->id,
        ));

        $this->assertSame(1, AiAutopilotRun::query()->where('tenant_id', (string) $tenant->id)->count());
    }

    public function test_idempotency_lock_allows_processing_again_after_lock_expiration(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create();

        AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Primary agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'metadata' => [],
            'is_active' => true,
        ]);

        $messageId = (string) Str::orderedUuid();

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('set')
            ->twice()
            ->with(Mockery::type('string'), '1', 'EX', 60, 'NX')
            // Simulates lock expiration between dispatches.
            ->andReturn('OK', 'OK');
        $redisConnection->shouldReceive('xadd')
            ->twice()
            ->with('ai.run.request', '*', Mockery::type('array'))
            ->andReturn('1-0', '2-0');
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::type('string'))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->with(config('gateway.redis.connection', 'gateway'))
            ->andReturn($redisConnection);

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => (string) $ticket->id,
                'message_id' => $messageId,
                'body' => 'First message',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: (string) $ticket->id,
        ));

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => (string) $ticket->id,
                'message_id' => $messageId,
                'body' => 'Second message after expiration',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: (string) $ticket->id,
        ));

        $this->assertSame(2, AiAutopilotRun::query()->where('tenant_id', (string) $tenant->id)->count());
    }

    public function test_trigger_observer_rebuilds_tenant_trigger_cache_on_save_and_delete(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $agent = AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Cacheable agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'metadata' => [],
            'is_active' => true,
        ]);

        $cacheKey = AiAgentTriggerObserver::cacheKey((string) $tenant->id);
        Cache::put($cacheKey, collect([['stale' => true]]), 3600);

        $trigger = AiAgentTrigger::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'agent_id' => (string) $agent->id,
            'type' => AutopilotTriggerType::INBOUND_MESSAGE->value,
            'config' => [],
            'status' => 'active',
        ]);

        $cachedAfterSave = Cache::get($cacheKey);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $cachedAfterSave);
        $this->assertSame(1, $cachedAfterSave->count());
        $this->assertSame((string) $trigger->id, (string) $cachedAfterSave->first()->id);

        $trigger->delete();

        $cachedAfterDelete = Cache::get($cacheKey);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $cachedAfterDelete);
        $this->assertCount(0, $cachedAfterDelete);
    }

    /**
     * OS-020: When no active agent (and no trigger) exists, the listener emits
     * ai.processing.rejected via chat activity and returns without creating a run.
     *
     * Business rule: autopilot cannot proceed without an agent capable of handling
     * the message; the frontend must be notified so the user sees a clear status.
     */
    public function test_emits_rejected_activity_and_creates_no_run_when_no_active_agent(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create();
        $messageId = (string) Str::orderedUuid();

        // No agent created — fallback resolver will return null.

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('set')
            ->once()
            ->with(Mockery::type('string'), '1', 'EX', 60, 'NX')
            ->andReturn('OK');
        // xadd must NOT be called (no run dispatch)
        $redisConnection->shouldReceive('xadd')->never();
        // Rejected activity is broadcast to both ticket room and tenant room (2 publishes).
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::on(function (string $payload) use ($ticket, $tenant, $messageId): bool {
                $decoded = json_decode($payload, true);

                return is_array($decoded)
                    && ($decoded['event'] ?? null) === 'chat.activity'
                    && ($decoded['tenant_id'] ?? null) === (string) $tenant->id
                    && data_get($decoded, 'data.ticketId') === (string) $ticket->id
                    && data_get($decoded, 'data.subevents.0.type') === 'ai.processing.rejected'
                    && data_get($decoded, 'data.subevents.0.data.message_id') === $messageId;
            }))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->with(config('gateway.redis.connection', 'gateway'))
            ->andReturn($redisConnection);

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => (string) $ticket->id,
                'message_id' => $messageId,
                'body' => 'Hello, anyone there?',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: (string) $ticket->id,
        ));

        $this->assertSame(0, AiAutopilotRun::query()->where('tenant_id', (string) $tenant->id)->count());
    }

    public function test_rejects_with_configured_fallback_message_when_no_general_agent_is_available(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'settings_json' => [
                'channel_fallback_message' => 'Mensagem do canal deve vencer',
            ],
        ]);
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create();
        $messageId = (string) Str::orderedUuid();

        AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Qualifier only',
            'type' => 'qualifier',
            'model_id' => 'gpt-4o-mini',
            'fallback_message' => 'Vou te conectar com um especialista agora.',
            'metadata' => [],
            'is_active' => true,
        ]);

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('set')
            ->once()
            ->with(Mockery::type('string'), '1', 'EX', 60, 'NX')
            ->andReturn('OK');
        $redisConnection->shouldReceive('xadd')->never();
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::on(function (string $payload) use ($ticket, $tenant, $messageId): bool {
                $decoded = json_decode($payload, true);

                return is_array($decoded)
                    && ($decoded['event'] ?? null) === 'chat.activity'
                    && ($decoded['tenant_id'] ?? null) === (string) $tenant->id
                    && data_get($decoded, 'data.ticketId') === (string) $ticket->id
                    && data_get($decoded, 'data.subevents.0.type') === 'ai.processing.rejected'
                    && data_get($decoded, 'data.subevents.0.data.message_id') === $messageId
                    && data_get($decoded, 'data.subevents.0.data.reason') === 'Mensagem do canal deve vencer';
            }))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->with(config('gateway.redis.connection', 'gateway'))
            ->andReturn($redisConnection);

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => (string) $ticket->id,
                'message_id' => $messageId,
                'body' => 'Qual seu nome?',
                'instance_id' => (string) $instance->id,
                'source_type' => 'ticket',
            ],
            sourceId: (string) $ticket->id,
        ));

        $this->assertSame(0, AiAutopilotRun::query()->where('tenant_id', (string) $tenant->id)->count());
    }

    public function test_rejects_with_agent_fallback_when_integration_fallback_is_absent(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'settings_json' => [],
        ]);
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create();
        $messageId = (string) Str::orderedUuid();

        AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Qualifier only',
            'type' => 'qualifier',
            'model_id' => 'gpt-4o-mini',
            'fallback_message' => 'Fallback do agente',
            'metadata' => [],
            'is_active' => true,
        ]);

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('set')
            ->once()
            ->with(Mockery::type('string'), '1', 'EX', 60, 'NX')
            ->andReturn('OK');
        $redisConnection->shouldReceive('xadd')->never();
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::on(function (string $payload) use ($ticket, $tenant, $messageId): bool {
                $decoded = json_decode($payload, true);

                return is_array($decoded)
                    && ($decoded['event'] ?? null) === 'chat.activity'
                    && ($decoded['tenant_id'] ?? null) === (string) $tenant->id
                    && data_get($decoded, 'data.ticketId') === (string) $ticket->id
                    && data_get($decoded, 'data.subevents.0.type') === 'ai.processing.rejected'
                    && data_get($decoded, 'data.subevents.0.data.message_id') === $messageId
                    && data_get($decoded, 'data.subevents.0.data.reason') === 'Fallback do agente';
            }))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->with(config('gateway.redis.connection', 'gateway'))
            ->andReturn($redisConnection);

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => (string) $ticket->id,
                'message_id' => $messageId,
                'body' => 'Qual seu nome?',
                'instance_id' => (string) $instance->id,
                'source_type' => 'ticket',
            ],
            sourceId: (string) $ticket->id,
        ));

        $this->assertSame(0, AiAutopilotRun::query()->where('tenant_id', (string) $tenant->id)->count());
    }

    public function test_rejects_with_global_default_when_integration_and_agent_fallbacks_are_absent(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'settings_json' => [],
        ]);
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create();
        $messageId = (string) Str::orderedUuid();

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('set')
            ->once()
            ->with(Mockery::type('string'), '1', 'EX', 60, 'NX')
            ->andReturn('OK');
        $redisConnection->shouldReceive('xadd')->never();
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::on(function (string $payload) use ($ticket, $tenant, $messageId): bool {
                $decoded = json_decode($payload, true);

                return is_array($decoded)
                    && ($decoded['event'] ?? null) === 'chat.activity'
                    && ($decoded['tenant_id'] ?? null) === (string) $tenant->id
                    && data_get($decoded, 'data.ticketId') === (string) $ticket->id
                    && data_get($decoded, 'data.subevents.0.type') === 'ai.processing.rejected'
                    && data_get($decoded, 'data.subevents.0.data.message_id') === $messageId
                    && data_get($decoded, 'data.subevents.0.data.reason') === 'No momento nao consegui concluir seu atendimento automaticamente. Vou te conectar agora a um especialista comercial para dar continuidade.';
            }))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->with(config('gateway.redis.connection', 'gateway'))
            ->andReturn($redisConnection);

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => (string) $ticket->id,
                'message_id' => $messageId,
                'body' => 'Oi?',
                'instance_id' => (string) $instance->id,
                'source_type' => 'ticket',
            ],
            sourceId: (string) $ticket->id,
        ));

        $this->assertSame(0, AiAutopilotRun::query()->where('tenant_id', (string) $tenant->id)->count());
    }

    public function test_dispatch_payload_is_capped_at_twenty_kb_by_truncating_old_messages(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create([
            'contact_id' => (string) $contact->id,
        ]);

        AiAgent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'name' => 'Primary agent',
            'type' => 'general',
            'model_id' => 'gpt-4o-mini',
            'metadata' => [
                'tool_names' => ['send_message', 'read_ticket'],
            ],
            'is_active' => true,
        ]);

        for ($index = 0; $index < 14; $index++) {
            ChatMessage::factory()->create([
                'tenant_id' => (string) $tenant->id,
                'ticket_id' => (string) $ticket->id,
                'content' => str_repeat('Mensagem muito longa para teste de truncamento. ', 90),
                'direction' => $index % 2 === 0 ? 'incoming' : 'outgoing',
                'is_from_contact' => $index % 2 === 0,
            ]);
        }

        $messageId = (string) Str::orderedUuid();

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('set')
            ->once()
            ->with(Mockery::type('string'), '1', 'EX', 60, 'NX')
            ->andReturn('OK');
        $redisConnection->shouldReceive('xadd')
            ->once()
            ->with('ai.run.request', '*', Mockery::on(function (array $payload): bool {
                $context = json_decode((string) ($payload['context'] ?? ''), true);
                $lastMessages = is_array($context) && is_array($context['last_messages'] ?? null)
                    ? $context['last_messages']
                    : [];

                try {
                    $payloadSize = strlen(json_encode($payload, JSON_THROW_ON_ERROR));
                } catch (\JsonException) {
                    return false;
                }

                return isset($payload['prompt'], $payload['context'], $payload['tools'], $payload['hydrated_at'])
                    && is_array(json_decode((string) ($payload['tools'] ?? ''), true))
                    && is_array($context)
                    && count($lastMessages) >= 2
                    && count($lastMessages) < 14
                    && $payloadSize <= 20000;
            }))
            ->andReturn('1-0');
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::type('string'))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->with(config('gateway.redis.connection', 'gateway'))
            ->andReturn($redisConnection);

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => (string) $ticket->id,
                'message_id' => $messageId,
                'body' => 'Mensagem para disparo',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: (string) $ticket->id,
        ));
    }
}
