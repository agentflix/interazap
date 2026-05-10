<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Services\ChatChannelConnector;
use Domain\Platform\Services\UazapiGatewayService;
use Domain\Shared\Infrastructure\Gateway\GatewayHttpClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ChatChannelConnectorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_connect_returns_qr_payload(): void
    {
        $tenant = \Domain\Platform\Models\PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'uazapi',
            'settings_json' => ['token' => 'tok-1'],
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('connectInstance')
            ->once()
            ->with('tok-1', ['mode' => 'qr'])
            ->andReturn([
                'qrcode' => 'BASE64DATA',
                'expires_at' => now()->addMinutes(5)->toIso8601String(),
            ]);

        $connector = new ChatChannelConnector($gateway, Mockery::mock(GatewayHttpClient::class));
        $result = $connector->connect($instance, 'qr');

        $this->assertSame('qr', $result['mode']);
        $this->assertStringStartsWith('data:image/png;base64,', $result['qr_code'] ?? '');
        $this->assertSame('uazapi', $result['provider']);
    }

    public function test_connect_returns_pair_code_payload(): void
    {
        $tenant = \Domain\Platform\Models\PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'uazapi',
            'settings_json' => ['token' => 'tok-2'],
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('connectInstance')
            ->once()
            ->with('tok-2', ['mode' => 'pair', 'phone' => '5511999999999'])
            ->andReturn([
                'pairCode' => '12345678',
                'expires_at' => now()->addMinutes(5)->toIso8601String(),
            ]);

        $connector = new ChatChannelConnector($gateway, Mockery::mock(GatewayHttpClient::class));
        $result = $connector->connect($instance, 'pair', '5511999999999');

        $this->assertSame('pair', $result['mode']);
        $this->assertSame('12345678', $result['pair_code']);
        $this->assertSame('5511999999999', $result['phone']);
    }

    public function test_connect_throws_when_provider_invalid_or_token_missing(): void
    {
        $tenant = \Domain\Platform\Models\PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'uazapi',
            'settings_json' => [],
        ]);

        $connector = new ChatChannelConnector(Mockery::mock(UazapiGatewayService::class), Mockery::mock(GatewayHttpClient::class));

        $this->expectException(RuntimeException::class);
        $connector->connect($instance, 'qr');
    }

    public function test_configure_webhook_calls_gateway_for_uazapi(): void
    {
        $tenant = \Domain\Platform\Models\PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'uazapi',
            'settings_json' => ['token' => 'tok-3'],
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('configureWebhook')
            ->once()
            ->with('tok-3', Mockery::type('array'))
            ->andReturn(['ok' => true]);

        $connector = new ChatChannelConnector($gateway, Mockery::mock(GatewayHttpClient::class));
        $response = $connector->configureWebhook($instance, 'https://app.test/webhook');

        $this->assertSame(['ok' => true], $response);
    }

    public function test_connect_telegram_returns_normalized_payload(): void
    {
        $tenant = \Domain\Platform\Models\PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'telegram',
            'settings_json' => ['bot_token' => '123456:ABC-DEF'],
        ]);

        $uazapi = Mockery::mock(UazapiGatewayService::class);
        $http = Mockery::mock(GatewayHttpClient::class);

        // validate-token response
        $http->shouldReceive('post')
            ->once()
            ->with('/telegram/validate-token', ['bot_token' => '123456:ABC-DEF'])
            ->andReturn(['id' => 987654, 'username' => 'test_bot']);

        // set-webhook response
        $http->shouldReceive('post')
            ->once()
            ->with('/telegram/set-webhook', Mockery::on(function (array $payload) {
                return isset($payload['bot_token'], $payload['webhook_url'], $payload['webhook_secret']);
            }))
            ->andReturn(['ok' => true]);

        $connector = new ChatChannelConnector($uazapi, $http);
        $result = $connector->connect($instance, 'qr');

        // Contract fields
        $this->assertSame('telegram', $result['mode']);
        $this->assertNull($result['qr_code']);
        $this->assertNull($result['pair_code']);
        $this->assertNotNull($result['expires_at']);
        $this->assertSame('telegram', $result['provider']);

        // Telegram-specific fields
        $this->assertSame('connected', $result['status']);
        $this->assertSame('test_bot', $result['bot_username']);
        $this->assertSame(987654, $result['bot_id']);
    }

    public function test_connect_telegram_throws_when_bot_token_missing(): void
    {
        $tenant = \Domain\Platform\Models\PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'telegram',
            'settings_json' => [],
        ]);

        $connector = new ChatChannelConnector(
            Mockery::mock(UazapiGatewayService::class),
            Mockery::mock(GatewayHttpClient::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bot token ausente para instância Telegram.');
        $connector->connect($instance, 'qr');
    }
}
