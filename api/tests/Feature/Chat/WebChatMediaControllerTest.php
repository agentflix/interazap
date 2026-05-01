<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use Domain\Chat\Models\ChatSession;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\WebChatJwtService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

final class WebChatMediaControllerTest extends TestCase
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

    public function test_upload_valido_retorna_201_com_url(): void
    {
        Storage::fake('public');

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

        $file = UploadedFile::fake()->image('foto.png', 100, 100);

        $response = $this->postJson('/api/webchat/media', [
            'token' => $token,
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['url', 'file_name', 'mime_type', 'size'],
            ]);

        $url = $response->json('data.url');
        $this->assertNotEmpty($url);

        // Extrair o path relativo da URL para verificar no Storage::fake
        $parsedPath = ltrim(parse_url($url, PHP_URL_PATH), '/');
        // Remove o prefixo 'storage/' se presente
        $storagePath = preg_replace('#^storage/#', '', $parsedPath);
        Storage::disk('public')->assertExists($storagePath);
    }

    public function test_token_invalido_retorna_401(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('foto.png');

        $response = $this->postJson('/api/webchat/media', [
            'token' => 'token.invalido.assinatura',
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_token_expirado_retorna_401(): void
    {
        Storage::fake('public');

        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenantId,
            'channel' => 'web',
        ]);
        $session = ChatSession::factory()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
        ]);

        $expiredToken = $this->buildExpiredToken(
            (string) $session->id,
            $this->tenantId,
            $session->contact_id,
            (string) $session->ticket_id,
        );

        $file = UploadedFile::fake()->image('foto.png');

        $response = $this->postJson('/api/webchat/media', [
            'token' => $expiredToken,
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_sessao_nao_encontrada_retorna_404(): void
    {
        Storage::fake('public');

        $nonExistentSessionId = (string) \Illuminate\Support\Str::orderedUuid();
        $nonExistentTicketId = (string) \Illuminate\Support\Str::orderedUuid();

        $token = $this->jwtService->generateToken(
            $nonExistentSessionId,
            $this->tenantId,
            null,
            $nonExistentTicketId,
        );

        $file = UploadedFile::fake()->image('foto.png');

        $response = $this->postJson('/api/webchat/media', [
            'token' => $token,
            'file' => $file,
        ]);

        $response->assertStatus(404);
    }

    public function test_arquivo_muito_grande_retorna_422(): void
    {
        Storage::fake('public');

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

        // UploadedFile::fake()->create() recebe tamanho em KB; 10241 KB > 10 MB
        $file = UploadedFile::fake()->create('grande.jpg', 10241, 'image/jpeg');

        $response = $this->postJson('/api/webchat/media', [
            'token' => $token,
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrorFor('file');
    }

    public function test_tipo_mime_nao_permitido_retorna_422(): void
    {
        Storage::fake('public');

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

        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');

        $response = $this->postJson('/api/webchat/media', [
            'token' => $token,
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrorFor('file');
    }

    public function test_sem_arquivo_retorna_422(): void
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

        $response = $this->postJson('/api/webchat/media', [
            'token' => $token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrorFor('file');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Cria um token com `exp` no passado, assinado com a mesma chave
     * do serviço (via Reflection para acessar o segredo privado).
     */
    private function buildExpiredToken(
        string $sessionId,
        string $tenantId,
        ?string $contactId,
        string $ticketId,
    ): string {
        $reflection = new ReflectionClass($this->jwtService);

        // Recuperar segredo privado
        $secretProp = $reflection->getProperty('secret');
        $secretProp->setAccessible(true);
        $secret = (string) $secretProp->getValue($this->jwtService);

        // Recuperar issuer privado
        $issuerProp = $reflection->getProperty('issuer');
        $issuerProp->setAccessible(true);
        $issuer = (string) $issuerProp->getValue($this->jwtService);

        $encode = static function (string $data): string {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        };

        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR);
        $payload = json_encode([
            'sub' => $sessionId,
            'iss' => $issuer,
            'iat' => time() - 7200,
            'exp' => time() - 3600, // expirado há 1 hora
            'session_id' => $sessionId,
            'tenant_id' => $tenantId,
            'contact_id' => $contactId,
            'ticket_id' => $ticketId,
            'type' => 'webchat',
        ], JSON_THROW_ON_ERROR);

        $headerEncoded = $encode($header);
        $payloadEncoded = $encode($payload);
        $signature = hash_hmac('sha256', $headerEncoded.'.'.$payloadEncoded, $secret, true);

        return $headerEncoded.'.'.$payloadEncoded.'.'.$encode($signature);
    }
}
