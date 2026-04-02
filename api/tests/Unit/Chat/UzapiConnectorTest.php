<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Platform\Services\UazapiGatewayService;
use Domain\Shared\Infrastructure\WhatsApp\Connectors\UzapiConnector;
use Illuminate\Support\Facades\Date;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class UzapiConnectorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_connect_returns_qr_payload(): void
    {
        $expiresAt = Date::now()->addMinutes(5);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('connectInstance')
            ->once()
            ->with('tok-1', ['mode' => 'qr'])
            ->andReturn([
                'qrcode' => 'BASE64DATA',
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

        $connector = new UzapiConnector($gateway, 'tok-1');
        $result = $connector->connect('qr');

        $this->assertSame('qr', $result->mode);
        $this->assertStringStartsWith('data:image/png;base64,', $result->qrCode ?? '');
        $this->assertSame('uazapi', $result->provider);
        $this->assertSame($expiresAt->toIso8601String(), $result->expiresAt->toIso8601String());
    }

    public function test_connect_returns_pair_payload_and_sanitizes_phone(): void
    {
        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('connectInstance')
            ->once()
            ->with('tok-1', ['mode' => 'pair', 'phone' => '5511999999999'])
            ->andReturn([
                'pairCode' => '12345678',
            ]);

        $connector = new UzapiConnector($gateway, 'tok-1');
        $result = $connector->connect('pair', '(55) 11 99999-9999');

        $this->assertSame('pair', $result->mode);
        $this->assertSame('12345678', $result->pairCode);
        $this->assertSame('(55) 11 99999-9999', $result->phone);
    }

    public function test_connect_throws_when_missing_qr_and_pair_code(): void
    {
        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('connectInstance')
            ->once()
            ->andReturn([]);

        $connector = new UzapiConnector($gateway, 'tok-1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gateway não retornou QR code nem código de pareamento');

        $connector->connect('qr');
    }

    public function test_disconnect_returns_success(): void
    {
        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('disconnectInstance')
            ->once()
            ->with('tok-1')
            ->andReturn(['success' => true]);

        $connector = new UzapiConnector($gateway, 'tok-1');

        $this->assertTrue($connector->disconnect());
    }

    public function test_restart_returns_success(): void
    {
        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('disconnectInstance')
            ->once()
            ->with('tok-1')
            ->andReturn(['success' => true]);

        $connector = new UzapiConnector($gateway, 'tok-1');

        $this->assertTrue($connector->restart());
    }

    public function test_configure_webhooks_returns_success(): void
    {
        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('configureWebhook')
            ->once()
            ->with('tok-1', Mockery::type('array'))
            ->andReturn(['success' => true]);

        $connector = new UzapiConnector($gateway, 'tok-1');

        $this->assertTrue($connector->configureWebhooks('https://app.test/webhook', ['messages']));
    }
}
