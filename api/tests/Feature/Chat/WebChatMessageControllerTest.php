<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use Domain\Ai\Events\AiRunRequested;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\WebChatJwtService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class WebChatMessageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $tenantId;

    private WebChatJwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = (string) PlatformTenant::factory()->create()->id;
        $this->jwtService = app(WebChatJwtService::class);
    }

    public function test_receives_message_and_dispatches_autopilot_responder(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'channel' => 'web',
        ]);
        $session = ChatSession::factory()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
        ]);

        $token = $this->jwtService->generateToken(
            (string) $session->id,
            $this->tenantId,
            $session->contact_id,
            (string) $session->ticket_id,
        );

        Event::fake([AiRunRequested::class]);

        $response = $this->postJson('/api/webchat/messages', [
            'token' => $token,
            'content' => 'Olá, preciso de ajuda',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['messageId'],
            ]);

        $messageId = $response->json('data.messageId');
        $this->assertNotEmpty($messageId);

        $message = ChatMessage::find($messageId);
        $this->assertNotNull($message);
        $this->assertEquals('Olá, preciso de ajuda', $message->content);
        $this->assertEquals($this->tenantId, $message->tenant_id);
        $this->assertEquals((string) $ticket->id, $message->ticket_id);
        $this->assertEquals('incoming', $message->direction);
        $this->assertEquals('webchat', $message->source);

        Event::assertDispatched(AiRunRequested::class, function ($event) use ($ticket): bool {
            return $event->tenantId === (string) $ticket->tenant_id
                && $event->ticketId === (string) $ticket->id
                && $event->body === 'Olá, preciso de ajuda';
        });
    }

    public function test_returns_401_for_invalid_token(): void
    {
        $response = $this->postJson('/api/webchat/messages', [
            'token' => 'invalid-token',
            'content' => 'Olá',
        ]);

        $response->assertStatus(401);
    }

    public function test_returns_400_when_content_is_empty(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'channel' => 'web',
        ]);
        $session = ChatSession::factory()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
        ]);

        $token = $this->jwtService->generateToken(
            (string) $session->id,
            $this->tenantId,
            $session->contact_id,
            (string) $ticket->id,
        );

        $response = $this->postJson('/api/webchat/messages', [
            'token' => $token,
            'content' => '',
        ]);

        $response->assertStatus(400);
    }
}
