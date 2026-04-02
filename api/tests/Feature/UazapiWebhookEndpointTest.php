<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class UazapiWebhookEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // evita chamadas externas (broadcast)
        Cache::flush();
    }

    public function test_ingests_webhook_and_persists_message(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $token = (string) Str::orderedUuid();

        ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'uazapi',
            'webhook_token' => $token,
            'settings_json' => ['token' => $token],
        ]);

        $payload = [
            'EventType' => 'messages',
            'message' => [
                'id' => 'msg-123',
                'chatid' => '5511999999999@wa.gw',
                'fromMe' => false,
                'text' => 'Olá do webhook',
                'type' => 'text',
            ],
            'chat' => ['phone' => '5511999999999'],
        ];

        $this->postJson("/api/webhooks/uazapi/instances/{$token}", $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('chat_messages', 1);
        $message = ChatMessage::query()->first();
        $this->assertSame('Olá do webhook', $message?->content);
    }

    public function test_idempotency_skips_duplicate_payload(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $token = (string) Str::orderedUuid();

        ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'uazapi',
            'webhook_token' => $token,
            'settings_json' => ['token' => $token],
        ]);

        $payload = [
            'EventType' => 'messages',
            'message' => [
                'id' => 'msg-dup',
                'chatid' => '5511999999999@wa.gw',
                'fromMe' => false,
                'text' => 'Mensagem duplicada',
                'type' => 'text',
            ],
        ];

        $this->postJson("/api/webhooks/uazapi/instances/{$token}", $payload)->assertOk();
        $this->postJson("/api/webhooks/uazapi/instances/{$token}", $payload)->assertOk();

        $this->assertDatabaseCount('shared_webhook_events', 1);
        $this->assertDatabaseCount('chat_messages', 1);
    }
}
