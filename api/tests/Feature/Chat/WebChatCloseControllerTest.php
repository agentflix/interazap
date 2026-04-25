<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use Domain\Chat\Models\ChatSession;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\WebChatJwtService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WebChatCloseControllerTest extends TestCase
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

    public function test_closes_webchat_ticket_with_valid_session_token(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'channel' => 'web',
            'status' => 'pending',
            'closed_at' => null,
        ]);
        $session = ChatSession::query()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
            'token' => (string) Str::uuid(),
            'last_activity_at' => now(),
        ]);

        $token = $this->jwtService->generateToken(
            (string) $session->id,
            $this->tenantId,
            $session->contact_id,
            (string) $session->ticket_id,
        );

        $response = $this->postJson('/api/webchat/close', [
            'token' => $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Ticket fechado')
            ->assertJsonPath('data.ticketId', (string) $ticket->id)
            ->assertJsonPath('data.status', 'closed');

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_returns_closed_state_idempotently_when_ticket_is_already_closed(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'channel' => 'web',
            'status' => 'closed',
            'closed_at' => now(),
        ]);
        $session = ChatSession::query()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
            'token' => (string) Str::uuid(),
            'last_activity_at' => now(),
        ]);

        $token = $this->jwtService->generateToken(
            (string) $session->id,
            $this->tenantId,
            $session->contact_id,
            (string) $session->ticket_id,
        );

        $closedAtBefore = $ticket->closed_at?->getTimestamp();

        $response = $this->postJson('/api/webchat/close', [
            'token' => $token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.ticketId', (string) $ticket->id)
            ->assertJsonPath('data.status', 'closed');

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertSame($closedAtBefore, $ticket->closed_at?->getTimestamp());
    }

    public function test_returns_unauthorized_for_invalid_token(): void
    {
        $response = $this->postJson('/api/webchat/close', [
            'token' => 'invalid-token',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Token inválido ou expirado');
    }

    public function test_returns_unauthorized_for_expired_token(): void
    {
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'channel' => 'web',
            'status' => 'pending',
            'closed_at' => null,
        ]);
        $session = ChatSession::query()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
            'token' => (string) Str::uuid(),
            'last_activity_at' => now(),
        ]);

        $token = $this->generateExpiredToken(
            (string) $session->id,
            $this->tenantId,
            $session->contact_id,
            (string) $session->ticket_id,
        );

        $response = $this->postJson('/api/webchat/close', [
            'token' => $token,
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Token inválido ou expirado');
    }

    private function generateExpiredToken(
        string $sessionId,
        string $tenantId,
        ?string $contactId,
        string $ticketId,
    ): string {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'sub' => $sessionId,
            'iss' => config('app.url', 'interazap'),
            'iat' => time() - 7200,
            'exp' => time() - 3600,
            'session_id' => $sessionId,
            'tenant_id' => $tenantId,
            'contact_id' => $contactId,
            'ticket_id' => $ticketId,
            'type' => 'webchat',
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $signature = hash_hmac('sha256', $headerEncoded.'.'.$payloadEncoded, $this->resolveJwtSecret(), true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded.'.'.$payloadEncoded.'.'.$signatureEncoded;
    }

    private function resolveJwtSecret(): string
    {
        $sharedJwtSecret = env('WEBCHAT_JWT_SECRET')
            ?: env('JWT_SECRET')
            ?: env('DEFAULT_TENANT_ID');

        return $sharedJwtSecret !== null && $sharedJwtSecret !== ''
            ? (string) $sharedJwtSecret
            : (string) config('app.key');
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}