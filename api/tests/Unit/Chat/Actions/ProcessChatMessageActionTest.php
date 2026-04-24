<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Actions;

use Domain\Chat\Actions\ProcessChatMessageAction;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatMessageExtended;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class ProcessChatMessageActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ProcessChatMessageAction $action;
    private MockInterface $mockActivityBroadcast;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockActivityBroadcast = Mockery::mock(ChatActivityBroadcastService::class);
        $this->action = new ProcessChatMessageAction($this->mockActivityBroadcast);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_emitNewMessageEvent_broadcasts_text_message(): void
    {
        $ticket = ChatTicket::factory()->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'content' => 'Olá, tudo bem?',
            'type' => 'text',
        ]);

        $this->mockActivityBroadcast
            ->shouldReceive('emitMessageReceived')
            ->once()
            ->withArgs(function ($chatId, $payload) use ($ticket, $message): bool {
                return $chatId === (string) $ticket->id
                    && ($payload['message']['id'] ?? null) === (string) $message->id
                    && ($payload['message']['content'] ?? null) === 'Olá, tudo bem?'
                    && ($payload['message']['type'] ?? null) === 'text';
            });

        $this->action->emitNewMessageEvent($message, $ticket);
        $this->addToAssertionCount(1);
    }

    public function test_emitNewMessageEvent_broadcasts_media_message_with_file_fields(): void
    {
        $ticket = ChatTicket::factory()->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'content' => 'Minha foto',
            'type' => 'image',
        ]);

        // Override the extended record created by the factory (null file_url)
        // with real file data. The factory creates an extended row via flushPendingExtended().
        $message->extended->update([
            'file_url' => 'http://localhost/storage/chat/webchat/tenant1/photo.jpg',
            'file_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 204800,
        ]);

        $this->mockActivityBroadcast
            ->shouldReceive('emitMessageReceived')
            ->once()
            ->withArgs(function ($chatId, $payload) use ($ticket): bool {
                $msg = $payload['message'] ?? [];

                return $chatId === (string) $ticket->id
                    && ($msg['file_url'] ?? null) === 'http://localhost/storage/chat/webchat/tenant1/photo.jpg'
                    && ($msg['file_name'] ?? null) === 'photo.jpg'
                    && ($msg['mime_type'] ?? null) === 'image/jpeg'
                    && ($msg['file_size'] ?? null) === 204800;
            });

        $this->action->emitNewMessageEvent($message, $ticket);
        $this->addToAssertionCount(1);
    }

    public function test_emitNewMessageEvent_without_ticket_does_not_fail(): void
    {
        $ticket = ChatTicket::factory()->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'content' => 'Mensagem sem ticket',
            'type' => 'text',
        ]);

        $this->mockActivityBroadcast
            ->shouldReceive('emitMessageReceived')
            ->once()
            ->withArgs(fn ($chatId, $payload, $ticketData): bool =>
                $chatId === (string) $message->ticket_id
                    && $ticketData === null
                    && ($payload['message']['id'] ?? null) === (string) $message->id
            );

        $this->action->emitNewMessageEvent($message, null);
        $this->addToAssertionCount(1);
    }
}
