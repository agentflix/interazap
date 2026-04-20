<?php

declare(strict_types=1);

use Domain\Chat\Jobs\ChatWebhookIngressJob;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatWebhookEvent;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| TASK-T24 — Telegram Webhook Ingestion Tests
|--------------------------------------------------------------------------
| Testa o endpoint POST /api/webhooks/telegram/instances/{token}
| cobrindo cenários de mensagem de texto, foto, edição, rejeição
| por token inválido, idempotência e criação de contato CRM.
*/

it('processes valid text message webhook', function (): void {
    Queue::fake([ChatWebhookIngressJob::class]);

    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'telegram',
        'status' => 'connected',
        'is_active' => true,
        'webhook_token' => 'tg-test-token-text',
        'settings_json' => ['bot_token' => 'fake-bot-token', 'bot_id' => 123456],
    ]);

    $payload = [
        'update_id' => 100001,
        'message' => [
            'message_id' => 1,
            'chat' => ['id' => 999888, 'type' => 'private'],
            'from' => ['id' => 999888, 'first_name' => 'João', 'last_name' => 'Silva', 'username' => 'joaosilva'],
            'text' => 'Olá, preciso de ajuda!',
            'date' => time(),
        ],
    ];

    postJson("/api/webhooks/telegram/instances/{$instance->webhook_token}", $payload)
        ->assertOk()
        ->assertJsonFragment(['success' => true]);

    expect(ChatWebhookEvent::query()
        ->where('provider', 'telegram')
        ->where('tenant_id', (string) $tenant->id)
        ->exists()
    )->toBeTrue();

    Queue::assertPushed(ChatWebhookIngressJob::class);
});

it('processes photo message webhook', function (): void {
    Queue::fake([ChatWebhookIngressJob::class]);

    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'telegram',
        'status' => 'connected',
        'is_active' => true,
        'webhook_token' => 'tg-test-token-photo',
        'settings_json' => ['bot_token' => 'fake-bot-token', 'bot_id' => 123456],
    ]);

    $payload = [
        'update_id' => 100002,
        'message' => [
            'message_id' => 2,
            'chat' => ['id' => 999888, 'type' => 'private'],
            'from' => ['id' => 999888, 'first_name' => 'Maria'],
            'photo' => [
                ['file_id' => 'small_photo_id', 'file_unique_id' => 'a1', 'width' => 90, 'height' => 90, 'file_size' => 1024],
                ['file_id' => 'medium_photo_id', 'file_unique_id' => 'a2', 'width' => 320, 'height' => 320, 'file_size' => 8192],
                ['file_id' => 'large_photo_id', 'file_unique_id' => 'a3', 'width' => 800, 'height' => 800, 'file_size' => 32768],
            ],
            'caption' => 'Veja essa imagem',
            'date' => time(),
        ],
    ];

    postJson("/api/webhooks/telegram/instances/{$instance->webhook_token}", $payload)
        ->assertOk()
        ->assertJsonFragment(['success' => true]);

    $event = ChatWebhookEvent::query()
        ->where('provider', 'telegram')
        ->where('tenant_id', (string) $tenant->id)
        ->first();

    expect($event)->not->toBeNull();

    $eventPayload = is_array($event->payload) ? $event->payload : json_decode($event->payload, true);
    expect($eventPayload['message']['type'])->toBe('file');

    Queue::assertPushed(ChatWebhookIngressJob::class);
});

it('processes edited message webhook', function (): void {
    Queue::fake([ChatWebhookIngressJob::class]);

    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'telegram',
        'status' => 'connected',
        'is_active' => true,
        'webhook_token' => 'tg-test-token-edited',
        'settings_json' => ['bot_token' => 'fake-bot-token'],
    ]);

    $payload = [
        'update_id' => 100003,
        'edited_message' => [
            'message_id' => 5,
            'chat' => ['id' => 999888, 'type' => 'private'],
            'from' => ['id' => 999888, 'first_name' => 'João'],
            'text' => 'Mensagem editada',
            'date' => time() - 60,
            'edit_date' => time(),
        ],
    ];

    postJson("/api/webhooks/telegram/instances/{$instance->webhook_token}", $payload)
        ->assertOk()
        ->assertJsonFragment(['success' => true]);

    $event = ChatWebhookEvent::query()
        ->where('provider', 'telegram')
        ->where('tenant_id', (string) $tenant->id)
        ->first();

    expect($event)->not->toBeNull();

    $eventPayload = is_array($event->payload) ? $event->payload : json_decode($event->payload, true);
    expect($eventPayload['event_type'])->toBe('messages_update');

    Queue::assertPushed(ChatWebhookIngressJob::class);
});

it('rejects webhook for non-existent token', function (): void {
    postJson('/api/webhooks/telegram/instances/nonexistent-random-token', [
        'update_id' => 100004,
        'message' => [
            'message_id' => 10,
            'chat' => ['id' => 111, 'type' => 'private'],
            'from' => ['id' => 111, 'first_name' => 'Test'],
            'text' => 'Ping',
            'date' => time(),
        ],
    ])->assertForbidden();
});

it('rejects webhook for non-telegram instance', function (): void {
    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'uazapi',
        'status' => 'connected',
        'is_active' => true,
        'webhook_token' => 'tg-test-uazapi-instance',
        'settings_json' => ['token' => 'uazapi-token'],
    ]);

    postJson("/api/webhooks/telegram/instances/{$instance->webhook_token}", [
        'update_id' => 100005,
        'message' => [
            'message_id' => 11,
            'chat' => ['id' => 222, 'type' => 'private'],
            'from' => ['id' => 222, 'first_name' => 'Hacker'],
            'text' => 'Tentativa indevida',
            'date' => time(),
        ],
    ])->assertForbidden();
});

it('creates webhook event with chat_id as identifier for contact without phone', function (): void {
    Queue::fake([ChatWebhookIngressJob::class]);

    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'telegram',
        'status' => 'connected',
        'is_active' => true,
        'webhook_token' => 'tg-test-token-nophone',
        'settings_json' => ['bot_token' => 'fake-bot-token'],
    ]);

    $payload = [
        'update_id' => 100006,
        'message' => [
            'message_id' => 20,
            'chat' => ['id' => 777666, 'type' => 'private'],
            'from' => ['id' => 777666, 'first_name' => 'SemTelefone', 'username' => 'semtel'],
            'text' => 'Olá sem telefone',
            'date' => time(),
        ],
    ];

    postJson("/api/webhooks/telegram/instances/{$instance->webhook_token}", $payload)
        ->assertOk();

    $event = ChatWebhookEvent::query()
        ->where('provider', 'telegram')
        ->where('tenant_id', (string) $tenant->id)
        ->first();

    expect($event)->not->toBeNull();

    $eventPayload = is_array($event->payload) ? $event->payload : json_decode($event->payload, true);
    expect($eventPayload['chat']['id'])->toBe('777666');
    expect($eventPayload['message']['chatid'])->toBe('777666');
});

it('handles duplicate update_id with idempotency', function (): void {
    Queue::fake([ChatWebhookIngressJob::class]);

    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'telegram',
        'status' => 'connected',
        'is_active' => true,
        'webhook_token' => 'tg-test-token-idempotency',
        'settings_json' => ['bot_token' => 'fake-bot-token'],
    ]);

    $payload = [
        'update_id' => 200001,
        'message' => [
            'message_id' => 30,
            'chat' => ['id' => 555444, 'type' => 'private'],
            'from' => ['id' => 555444, 'first_name' => 'Duplicado'],
            'text' => 'Mensagem repetida',
            'date' => time(),
        ],
    ];

    // First request — should create event
    postJson("/api/webhooks/telegram/instances/{$instance->webhook_token}", $payload)
        ->assertOk()
        ->assertJsonFragment(['success' => true]);

    // Second request — same payload, should be idempotent
    postJson("/api/webhooks/telegram/instances/{$instance->webhook_token}", $payload)
        ->assertOk()
        ->assertJsonFragment(['success' => true]);

    $eventsCount = ChatWebhookEvent::query()
        ->where('provider', 'telegram')
        ->where('tenant_id', (string) $tenant->id)
        ->count();

    expect($eventsCount)->toBe(1);
});

it('ignores update without message or edited_message', function (): void {
    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'telegram',
        'status' => 'connected',
        'is_active' => true,
        'webhook_token' => 'tg-test-token-empty-update',
        'settings_json' => ['bot_token' => 'fake-bot-token'],
    ]);

    $payload = [
        'update_id' => 300001,
        'callback_query' => [
            'id' => 'cb-1',
            'from' => ['id' => 123, 'first_name' => 'Bot'],
            'data' => 'button_click',
        ],
    ];

    postJson("/api/webhooks/telegram/instances/{$instance->webhook_token}", $payload)
        ->assertOk()
        ->assertJsonFragment(['success' => true, 'ignored' => true]);
});
