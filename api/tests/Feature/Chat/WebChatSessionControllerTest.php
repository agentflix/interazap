<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use Domain\Chat\Models\ChatSession;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\WebChatJwtService;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class WebChatSessionControllerTest extends TestCase
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

    public function test_creates_new_webchat_session_and_returns_token(): void
    {
        $response = $this->postJson('/api/webchat/sessions', [
            'tenant_id' => $this->tenantId,
            'visitor_name' => 'João Silva',
            'visitor_email' => 'joao@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'sessionId',
                    'ticketId',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.sessionId'));
        $this->assertNotEmpty($response->json('data.ticketId'));

        $sessionId = $response->json('data.sessionId');
        $session = ChatSession::find($sessionId);
        $this->assertNotNull($session);
        $this->assertEquals($this->tenantId, $session->tenant_id);
    }

    public function test_returns_existing_session_when_token_is_provided(): void
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

        $response = $this->postJson('/api/webchat/sessions', [
            'tenant_id' => $this->tenantId,
            'token' => $token,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.sessionId', (string) $session->id)
            ->assertJsonPath('data.ticketId', (string) $session->ticket_id);
    }

    public function test_shows_session_by_id(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'channel' => 'web',
        ]);
        $contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Maria',
        ]);
        $session = ChatSession::factory()->create([
            'tenant_id' => $this->tenantId,
            'contact_id' => $contact->id,
            'ticket_id' => $ticket->id,
        ]);

        $response = $this->getJson("/api/webchat/sessions/{$session->id}?tenant_id={$this->tenantId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'token',
                    'ticketId',
                    'contactId',
                    'clientInfo',
                    'lastActivityAt',
                    'createdAt',
                ],
            ])
            ->assertJsonPath('data.id', (string) $session->id);
    }

    public function test_returns_404_for_non_existent_session(): void
    {
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $response = $this->getJson("/api/webchat/sessions/{$nonExistentId}?tenant_id={$this->tenantId}");

        $response->assertStatus(404);
    }
}
