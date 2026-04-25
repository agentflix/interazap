<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Services\ChatChannelConnector;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Infrastructure\Gateway\GatewayHttpClient;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Mocks\FakeGatewayHttpClient;

/*
|--------------------------------------------------------------------------
| TASK-T24 — Telegram Connection / Disconnection Tests
|--------------------------------------------------------------------------
| Testa os fluxos de connect/disconnect de instância Telegram via
| ChatChannelConnector e os endpoints HTTP de criação de canal.
*/

it('connects telegram instance with valid bot token', function (): void {
    $fakeGateway = new FakeGatewayHttpClient;

    // Mock getMe (validate-token)
    $fakeGateway->fake('POST', '/telegram/validate-token', [
        'id' => 987654321,
        'is_bot' => true,
        'first_name' => 'InteraZapBot',
        'username' => 'interazap_bot',
    ]);

    // Mock setWebhook
    $fakeGateway->fake('POST', '/telegram/set-webhook', [
        'ok' => true,
        'result' => true,
        'description' => 'Webhook was set',
    ]);

    $this->app->instance(GatewayHttpClient::class, $fakeGateway);

    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'telegram',
        'status' => 'disconnected',
        'is_active' => true,
        'settings_json' => ['bot_token' => 'valid-bot-token-123'],
    ]);

    /** @var ChatChannelConnector $connector */
    $connector = app(ChatChannelConnector::class);

    $result = $connector->connect($instance, 'qr');

    expect($result['status'])->toBe('connected');
    expect($result['bot_username'])->toBe('interazap_bot');
    expect($result['bot_id'])->toBe(987654321);

    $instance->refresh();
    expect($instance->status)->toBe('connected');
    expect($instance->settings_json['bot_id'])->toBe(987654321);
    expect($instance->settings_json['bot_username'])->toBe('interazap_bot');
    expect($instance->settings_json['webhook_secret'])->not->toBeEmpty();
    expect($instance->settings_json['bot_token'])->toBe('valid-bot-token-123');

    // Verify gateway calls
    $calls = $fakeGateway->calls();
    expect($calls)->toHaveCount(2);
    expect($calls[0]['endpoint'])->toBe('/telegram/validate-token');
    expect($calls[0]['payload']['bot_token'])->toBe('valid-bot-token-123');
    expect($calls[1]['endpoint'])->toBe('/telegram/set-webhook');
    expect($calls[1]['payload']['bot_token'])->toBe('valid-bot-token-123');
    expect($calls[1]['payload'])->toHaveKey('webhook_url');
    expect($calls[1]['payload'])->toHaveKey('webhook_secret');
});

it('fails connection with invalid bot token', function (): void {
    $fakeGateway = new FakeGatewayHttpClient;

    // Mock validate-token returning error (simulating exception)
    $fakeGateway->fake('POST', '/telegram/validate-token', []);

    $this->app->instance(GatewayHttpClient::class, $fakeGateway);

    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'telegram',
        'status' => 'disconnected',
        'settings_json' => ['bot_token' => 'invalid-token'],
    ]);

    /** @var ChatChannelConnector $connector */
    $connector = app(ChatChannelConnector::class);

    // validate-token returns empty (no 'id'), so RuntimeException
    expect(fn () => $connector->connect($instance, 'qr'))
        ->toThrow(RuntimeException::class, 'Resposta inválida do Telegram ao validar bot token.');
});

it('fails connection when bot_token is missing', function (): void {
    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'telegram',
        'status' => 'disconnected',
        'settings_json' => [],
    ]);

    /** @var ChatChannelConnector $connector */
    $connector = app(ChatChannelConnector::class);

    expect(fn () => $connector->connect($instance, 'qr'))
        ->toThrow(RuntimeException::class, 'Bot token ausente para instância Telegram.');
});

it('disconnects telegram instance', function (): void {
    $fakeGateway = new FakeGatewayHttpClient;

    $fakeGateway->fake('POST', '/telegram/delete-webhook', [
        'ok' => true,
        'result' => true,
        'description' => 'Webhook was deleted',
    ]);

    $this->app->instance(GatewayHttpClient::class, $fakeGateway);

    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'telegram',
        'status' => 'connected',
        'is_active' => true,
        'settings_json' => [
            'bot_token' => 'valid-bot-token-123',
            'bot_id' => 987654321,
            'bot_username' => 'interazap_bot',
            'webhook_secret' => 'secret-to-remove',
        ],
    ]);

    /** @var ChatChannelConnector $connector */
    $connector = app(ChatChannelConnector::class);

    $result = $connector->disconnect($instance);

    expect($result['status'])->toBe('disconnected');

    $instance->refresh();
    expect($instance->status)->toBe('disconnected');
    expect($instance->settings_json)->not->toHaveKey('webhook_secret');
    expect($instance->settings_json['bot_token'])->toBe('valid-bot-token-123');

    $calls = $fakeGateway->calls();
    expect($calls)->toHaveCount(1);
    expect($calls[0]['endpoint'])->toBe('/telegram/delete-webhook');
    expect($calls[0]['payload']['bot_token'])->toBe('valid-bot-token-123');
});

it('disconnects telegram instance gracefully when delete-webhook fails', function (): void {
    // FakeGatewayHttpClient sem fake registrado vai lançar exceção
    // mas o disconnectTelegram captura com try/catch
    $fakeGateway = new FakeGatewayHttpClient;
    $this->app->instance(GatewayHttpClient::class, $fakeGateway);

    $tenant = PlatformTenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenant->id,
        'provider' => 'telegram',
        'status' => 'connected',
        'is_active' => true,
        'settings_json' => [
            'bot_token' => 'valid-bot-token-123',
            'webhook_secret' => 'secret-123',
        ],
    ]);

    /** @var ChatChannelConnector $connector */
    $connector = app(ChatChannelConnector::class);

    $result = $connector->disconnect($instance);

    expect($result['status'])->toBe('disconnected');

    $instance->refresh();
    expect($instance->status)->toBe('disconnected');
    expect($instance->settings_json)->not->toHaveKey('webhook_secret');
});

it('validates telegram provider in ChatInstanceRequest accepts bot_token', function (): void {
    $user = AuthUser::factory()->create();

    $permManage = AuthPermission::query()->firstOrCreate(
        ['name' => 'chat.instances.manage', 'guard_name' => 'sanctum'],
        ['id' => (string) Str::orderedUuid()]
    );
    $user->givePermissionTo($permManage);

    Sanctum::actingAs($user, abilities: ['*']);

    $this->postJson('/api/channels', [
        'name' => 'Bot Telegram Teste',
        'provider' => 'telegram',
        'bot_token' => 'valid-test-bot-token',
    ])->assertCreated();

    $this->assertDatabaseHas('chat_instances', [
        'tenant_id' => (string) $user->tenant_id,
        'provider' => 'telegram',
        'name' => 'Bot Telegram Teste',
    ]);
});

it('requires bot_token for telegram provider', function (): void {
    $user = AuthUser::factory()->create();

    $permManage = AuthPermission::query()->firstOrCreate(
        ['name' => 'chat.instances.manage', 'guard_name' => 'sanctum'],
        ['id' => (string) Str::orderedUuid()]
    );
    $user->givePermissionTo($permManage);

    Sanctum::actingAs($user, abilities: ['*']);

    $this->postJson('/api/channels', [
        'name' => 'Bot Telegram Sem Token',
        'provider' => 'telegram',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['bot_token']);
});
