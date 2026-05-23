<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use Domain\Ai\Events\AiRunRequested;
use Domain\Chat\Jobs\ChatAutoReplyRespondJob;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\WebChatJwtService;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
        $plan = PlatformPlan::factory()->create(['ai_enabled' => false]);
        $this->tenantId = (string) PlatformTenant::factory()->create(['plan_id' => $plan->id])->id;
        $this->jwtService = app(WebChatJwtService::class);
    }

    public function test_receives_message_and_dispatches_autopilot_responder(): void
    {
        $plan = PlatformPlan::factory()->create(['ai_enabled' => true]);
        PlatformTenant::query()->where('id', $this->tenantId)->update(['plan_id' => $plan->id]);

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

        Bus::fake([ChatAutoReplyRespondJob::class]);
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

        $message = \Domain\Chat\Models\ChatMessage::query()->find($messageId);
        $this->assertNotNull($message);
        $this->assertEquals('Olá, preciso de ajuda', $message->content);
        $this->assertEquals($this->tenantId, $message->tenant_id);
        $this->assertEquals((string) $ticket->id, $message->ticket_id);
        $this->assertEquals('incoming', $message->direction);
        $this->assertEquals('webchat', $message->source);

        Event::assertDispatched(AiRunRequested::class, fn ($event): bool => $event->tenantId === (string) $ticket->tenant_id
            && $event->ticketId === (string) $ticket->id
            && $event->body === 'Olá, preciso de ajuda');
        Bus::assertNotDispatched(ChatAutoReplyRespondJob::class);
    }

    public function test_receives_message_and_does_not_dispatch_autopilot_when_plan_has_ai_disabled(): void
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

        Bus::fake([ChatAutoReplyRespondJob::class]);
        Event::fake([AiRunRequested::class]);

        $response = $this->postJson('/api/webchat/messages', [
            'token' => $token,
            'content' => 'Mensagem sem IA',
        ]);

        $response->assertStatus(201);

        Event::assertNotDispatched(AiRunRequested::class);
        Bus::assertDispatched(ChatAutoReplyRespondJob::class, fn (ChatAutoReplyRespondJob $job): bool => $this->readPrivateProperty($job, 'isFirstInteraction') === true
            && $this->readPrivateProperty($job, 'tenantId') === $this->tenantId
            && $this->readPrivateProperty($job, 'body') === 'Mensagem sem IA');
    }

    public function test_receives_message_and_skips_automation_when_ticket_is_under_human_takeover(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'channel' => 'web',
            'human_takeover_at' => now(),
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

        Bus::fake([ChatAutoReplyRespondJob::class]);
        Event::fake([AiRunRequested::class]);

        $response = $this->postJson('/api/webchat/messages', [
            'token' => $token,
            'content' => 'menu',
        ]);

        $response->assertStatus(201);

        Event::assertNotDispatched(AiRunRequested::class);
        Bus::assertNotDispatched(ChatAutoReplyRespondJob::class);
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

    private function readPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object, $property);

        return $reflection->getValue($object);
    }
}
