<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Shared\Infrastructure\WhatsApp\Adapters\FakeWhatsAppAdapter;
use Domain\Shared\Infrastructure\WhatsApp\Adapters\UzapiAdapter;
use Domain\Shared\Infrastructure\WhatsApp\Connectors\UzapiConnector;
use Domain\Shared\Infrastructure\WhatsApp\Factories\WhatsAppAdapterFactory;
use Domain\Shared\Infrastructure\WhatsApp\Normalizers\UzapiWebhookNormalizer;
use InvalidArgumentException;
use Tests\TestCase;

class WhatsAppAdapterFactoryTest extends TestCase
{
    public function test_make_provider_returns_fake_in_testing(): void
    {
        config()->set('services.whatsapp.use_real_provider', false);

        $factory = new WhatsAppAdapterFactory(app());
        $provider = $factory->makeProvider('uazapi', [
            'token' => 'tok-1',
            'base_url' => 'https://api.test',
        ]);

        $this->assertInstanceOf(FakeWhatsAppAdapter::class, $provider);
    }

    public function test_make_provider_returns_uzapi_when_real_provider_enabled(): void
    {
        config()->set('services.whatsapp.use_real_provider', true);

        $factory = new WhatsAppAdapterFactory(app());
        $provider = $factory->makeProvider('uazapi', [
            'token' => 'tok-1',
            'base_url' => 'https://api.test',
        ]);

        $this->assertInstanceOf(UzapiAdapter::class, $provider);
    }

    public function test_make_provider_throws_on_unknown_provider(): void
    {
        config()->set('services.whatsapp.use_real_provider', true);

        $factory = new WhatsAppAdapterFactory(app());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provedor não suportado');

        $factory->makeProvider('unknown', []);
    }

    public function test_make_connector_returns_instances(): void
    {
        $factory = new WhatsAppAdapterFactory(app());

        $uazapi = $factory->makeConnector('uazapi', ['token' => 'tok-1']);
        $this->assertInstanceOf(UzapiConnector::class, $uazapi);
    }

    public function test_make_normalizer_returns_instances(): void
    {
        $factory = new WhatsAppAdapterFactory(app());

        $this->assertInstanceOf(UzapiWebhookNormalizer::class, $factory->makeNormalizer('uazapi'));
    }

    public function test_get_supported_providers(): void
    {
        $factory = new WhatsAppAdapterFactory(app());

        $this->assertSame(['uazapi'], $factory->getSupportedProviders());
    }
}
