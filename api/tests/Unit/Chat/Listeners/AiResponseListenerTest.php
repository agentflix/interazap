<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Listeners;

use Domain\Ai\Events\AiResponseReceived;
use Domain\Chat\Listeners\AiResponseListener;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\WebChatRedisPublisher;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class AiResponseListenerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private MockInterface $mockPublisher;

    private AiResponseListener $listener;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockPublisher = Mockery::mock(WebChatRedisPublisher::class);
        $this->listener = new AiResponseListener($this->mockPublisher);
        $this->tenantId = (string) PlatformTenant::factory()->create()->id;
    }

    public function test_publishes_a_i_response_to_redis_when_session_id_is_in_context(): void
    {
        $ticket = ChatTicket::factory()->create(['tenant_id' => $this->tenantId]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
            'source' => 'ai',
            'direction' => 'outgoing',
            'content' => 'Olá! Como posso ajudar?',
        ]);
        $sessionId = 'session-123';

        $this->mockPublisher
            ->shouldReceive('publishAiResponse')
            ->once()
            ->withArgs(fn ($publishedSessionId, $messagePayload): bool => $publishedSessionId === $sessionId
                && $messagePayload['id'] === (string) $message->id
                && $messagePayload['content'] === 'Olá! Como posso ajudar?'
                && $messagePayload['source'] === 'ai');

        $event = new AiResponseReceived(
            tenantId: $this->tenantId,
            ticketId: (string) $ticket->id,
            messageId: (string) $message->id,
            context: ['session_id' => $sessionId, 'source' => 'webchat'],
        );

        $this->listener->handle($event);
        $this->addToAssertionCount(1);
    }

    public function test_publishes_media_message_with_file_fields(): void
    {
        $ticket = ChatTicket::factory()->create(['tenant_id' => $this->tenantId]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
            'source' => 'ai',
            'direction' => 'outgoing',
            'content' => 'Olha esta imagem!',
            'type' => 'image',
        ]);

        // The factory creates extended via flushPendingExtended() — override with file data
        $message->extended->update([
            'file_url' => 'http://localhost/storage/chat/webchat/tenant/img.png',
            'file_name' => 'img.png',
            'mime_type' => 'image/png',
            'file_size' => 102400,
        ]);

        $sessionId = 'session-media';

        $this->mockPublisher
            ->shouldReceive('publishAiResponse')
            ->once()
            ->withArgs(fn ($publishedSessionId, $messagePayload): bool => $publishedSessionId === $sessionId
                && ($messagePayload['file_url'] ?? null) === 'http://localhost/storage/chat/webchat/tenant/img.png'
                && ($messagePayload['file_name'] ?? null) === 'img.png'
                && ($messagePayload['mime_type'] ?? null) === 'image/png'
                && ($messagePayload['file_size'] ?? null) === 102400);

        $event = new AiResponseReceived(
            tenantId: $this->tenantId,
            ticketId: (string) $ticket->id,
            messageId: (string) $message->id,
            context: ['session_id' => $sessionId, 'source' => 'webchat'],
        );

        $this->listener->handle($event);
        $this->addToAssertionCount(1);
    }

    public function test_does_not_publish_when_session_id_is_not_in_context(): void
    {
        $ticket = ChatTicket::factory()->create(['tenant_id' => $this->tenantId]);
        ChatMessage::factory()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
            'source' => 'ai',
            'direction' => 'outgoing',
        ]);

        $this->mockPublisher->shouldNotReceive('publishAiResponse');

        $event = new AiResponseReceived(
            tenantId: $this->tenantId,
            ticketId: (string) $ticket->id,
            messageId: null,
            context: ['source' => 'whatsapp'],
        );

        $this->listener->handle($event);
        $this->addToAssertionCount(1);
    }

    public function test_skips_when_message_is_not_found(): void
    {
        $ticket = ChatTicket::factory()->create(['tenant_id' => $this->tenantId]);

        $this->mockPublisher->shouldNotReceive('publishAiResponse');

        $event = new AiResponseReceived(
            tenantId: $this->tenantId,
            ticketId: (string) $ticket->id,
            messageId: (string) Str::orderedUuid(),
            context: ['session_id' => 'session-123'],
        );

        $this->listener->handle($event);
        $this->addToAssertionCount(1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
