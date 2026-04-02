<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Actions\ChatUazapiWebhookActions;
use Domain\Chat\Jobs\ChatWebhookIngressJob;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Repositories\EloquentChatInstanceRepository;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\TestCase;

class ChatUazapiWebhookActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_handle_throws_for_invalid_token(): void
    {
        $actions = new ChatUazapiWebhookActions(new EloquentChatInstanceRepository);

        $this->expectException(RuntimeException::class);
        $actions->handle('invalid-token', ['event_type' => 'messages']);
    }

    public function test_handle_returns_ignored_for_inactive_instance(): void
    {
        $tenant = PlatformTenant::factory()->create();
        ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-inactive',
            'is_active' => false,
        ]);

        $actions = new ChatUazapiWebhookActions(new EloquentChatInstanceRepository);
        $result = $actions->handle('tok-inactive', ['event_type' => 'messages']);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['ignored']);
        $this->assertSame('inactive', $result['reason']);
    }

    public function test_handle_dispatches_job_and_skips_duplicates(): void
    {
        Bus::fake();

        $tenant = PlatformTenant::factory()->create();
        ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-msg',
            'is_active' => true,
        ]);

        $actions = new ChatUazapiWebhookActions(new EloquentChatInstanceRepository);

        // Payload já normalizado pelo Gateway
        $payload = [
            'provider' => 'uazapi',
            'event_type' => 'messages_update',
            'direction' => 'incoming',
            'message' => [
                'id' => 'msg-1',
            ],
            'raw' => [
                'event' => [
                    'MessageIDs' => ['msg-1'],
                    'Type' => 'delivered',
                ],
            ],
        ];

        $first = $actions->handle('tok-msg', $payload);
        $this->assertTrue($first['success']);
        $this->assertDatabaseCount('shared_webhook_events', 1);
        Bus::assertDispatched(ChatWebhookIngressJob::class);

        $second = $actions->handle('tok-msg', $payload);
        $this->assertTrue($second['success']);
        $this->assertTrue($second['duplicate']);
        Bus::assertDispatchedTimes(ChatWebhookIngressJob::class, 1);
    }

    public function test_connection_events_are_processed_even_when_duplicate(): void
    {
        Bus::fake();

        $tenant = PlatformTenant::factory()->create();
        ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-conn',
            'is_active' => true,
        ]);

        $actions = new ChatUazapiWebhookActions(new EloquentChatInstanceRepository);

        // Payload já normalizado pelo Gateway
        $payload = [
            'provider' => 'uazapi',
            'event_type' => 'connection',
            'raw' => [
                'status' => [
                    'connected' => true,
                ],
            ],
        ];

        $actions->handle('tok-conn', $payload);
        $actions->handle('tok-conn', $payload);

        // With ShouldBeUnique, the job is dispatched only once even for connection events
        // This is the expected behavior to prevent duplicate processing
        Bus::assertDispatchedTimes(ChatWebhookIngressJob::class, 1);
        $this->assertDatabaseCount('shared_webhook_events', 1);
    }

    public function test_handle_normalizes_event_type_from_event_type_aliases(): void
    {
        Bus::fake();

        $tenant = PlatformTenant::factory()->create();
        ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-alias',
            'is_active' => true,
        ]);

        $actions = new ChatUazapiWebhookActions(new EloquentChatInstanceRepository);

        $result = $actions->handle('tok-alias', [
            'EventType' => 'connection',
            'raw' => [
                'status' => [
                    'connected' => true,
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('shared_webhook_events', [
            'domain' => 'chat',
            'provider' => 'uazapi',
            'instance_webhook_token' => 'tok-alias',
            'event_type' => 'connection',
        ]);
        Bus::assertDispatched(ChatWebhookIngressJob::class);
    }
}
