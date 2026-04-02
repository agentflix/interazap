<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Actions\ChatMessageEditActions;
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

class ChatMessageEditActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_edit_updates_message_and_broadcasts(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-2',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
        ]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'direction' => 'outgoing',
            'content' => 'Old',
            'external_id' => 'ext-2',
            'edit_history' => [],
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('editMessage')
            ->once()
            ->with('tok-2', ['id' => 'ext-2', 'text' => 'New'])
            ->andReturn(['ok' => true]);

        Event::fake();
        Http::fake();
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $actions = new ChatMessageEditActions($gateway, $broadcast);
        $result = $actions->edit((string) $tenant->id, (string) $message->id, 'New');

        $this->assertTrue($result['success']);
        $message->refresh();
        $this->assertSame('New', $message->content);
        $this->assertTrue($message->is_edited);
        $this->assertCount(1, $message->edit_history ?? []);
    }

    public function test_edit_returns_error_when_missing_token_or_external_id(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => null,
        ]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'direction' => 'outgoing',
            'external_id' => null,
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldNotReceive('editMessage');

        Event::fake();
        Http::fake();
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $actions = new ChatMessageEditActions($gateway, $broadcast);
        $result = $actions->edit((string) $tenant->id, (string) $message->id, 'New');

        $this->assertFalse($result['success']);
        $this->assertSame('Missing token or external_id', $result['error']);
    }
}
