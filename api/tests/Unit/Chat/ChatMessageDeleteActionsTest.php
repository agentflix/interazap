<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Actions\ChatMessageDeleteActions;
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

class ChatMessageDeleteActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_delete_marks_message_as_deleted_and_broadcasts(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-1',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
        ]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'direction' => 'outgoing',
            'external_id' => 'ext-1',
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('deleteMessage')
            ->once()
            ->with('tok-1', ['id' => 'ext-1'])
            ->andReturn(['ok' => true]);

        Event::fake();
        Http::fake();
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);

        $actions = new ChatMessageDeleteActions($gateway, $broadcast);
        $result = $actions->delete((string) $tenant->id, (string) $message->id, (string) $user->id);

        $this->assertTrue($result['success']);
        $message->refresh();
        $this->assertSame('deleted', $message->status);
        $this->assertTrue($message->is_deleted);
        $this->assertSame((string) $user->id, $message->deleted_by);
    }

    public function test_delete_returns_error_when_missing_token_or_external_id(): void
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
        $gateway->shouldNotReceive('deleteMessage');

        Event::fake();
        Http::fake();
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);

        $actions = new ChatMessageDeleteActions($gateway, $broadcast);
        $result = $actions->delete((string) $tenant->id, (string) $message->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Missing token or external_id', $result['error']);
    }
}
