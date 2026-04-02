<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Actions\ChatMessageReactionActions;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ChatMessageReactionActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_react_adds_reaction_and_broadcasts(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-3',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
            'remote_jid' => '5511999999999@s.whatsapp.net',
        ]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'external_id' => 'ext-3',
            'reactions' => [],
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('reactToMessage')
            ->once()
            ->with('tok-3', [
                'number' => '5511999999999',
                'id' => 'ext-3',
                'text' => '👍',
            ])
            ->andReturn(['ok' => true]);

        Event::fake();
        Http::fake();
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $actions = new ChatMessageReactionActions($gateway, $broadcast);
        $result = $actions->react((string) $tenant->id, (string) $message->id, '👍');

        $this->assertTrue($result['success']);
        $message->refresh();
        $this->assertSame('👍', $message->reactions[0]['emoji'] ?? null);
    }

    public function test_react_removes_existing_reaction_when_empty(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-4',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
            'phone' => '5511888888888',
        ]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'external_id' => 'ext-4',
            'reactions' => [
                ['emoji' => '👀', 'from_me' => true, 'timestamp' => now()->toIso8601String()],
            ],
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('reactToMessage')
            ->once()
            ->andReturn(['ok' => true]);

        Event::fake();
        Http::fake();
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $actions = new ChatMessageReactionActions($gateway, $broadcast);
        $actions->react((string) $tenant->id, (string) $message->id, '');

        $message->refresh();
        $this->assertCount(0, $message->reactions ?? []);
    }

    public function test_react_returns_error_when_missing_payload_data(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => null,
        ]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'external_id' => null,
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldNotReceive('reactToMessage');

        Event::fake();
        Http::fake();
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $actions = new ChatMessageReactionActions($gateway, $broadcast);
        $result = $actions->react((string) $tenant->id, (string) $message->id, '👍');

        $this->assertFalse($result['success']);
        $this->assertSame('Missing token, number or external_id', $result['error']);
    }
}
