<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Services\ProviderResolver;
use Domain\Shared\Infrastructure\WhatsApp\Adapters\FakeWhatsAppAdapter;
use Domain\Shared\Infrastructure\WhatsApp\Factories\WhatsAppAdapterFactory;
use InvalidArgumentException;
use Tests\TestCase;

class ProviderResolverTest extends TestCase
{
    public function test_resolve_provider_returns_fake_in_testing(): void
    {
        config()->set('services.whatsapp.use_real_provider', false);

        $instance = new ChatInstance([
            'provider' => 'uazapi',
            'settings_json' => ['token' => 'tok-1'],
        ]);

        $resolver = new ProviderResolver(new WhatsAppAdapterFactory(app()));
        $provider = $resolver->resolveProvider($instance);

        $this->assertInstanceOf(FakeWhatsAppAdapter::class, $provider);
    }

    public function test_resolve_connector_throws_when_provider_is_zapi(): void
    {
        $instance = new ChatInstance([
            'provider' => 'zapi',
            'settings_json' => [
                'instance_id' => 'inst-1',
                'token_id' => 'tok-1',
            ],
        ]);

        $resolver = new ProviderResolver(new WhatsAppAdapterFactory(app()));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provedor não suportado');

        $resolver->resolveConnector($instance);
    }

    public function test_resolve_provider_throws_when_missing_uazapi_token(): void
    {
        $instance = new ChatInstance([
            'provider' => 'uazapi',
            'settings_json' => [],
        ]);

        $resolver = new ProviderResolver(new WhatsAppAdapterFactory(app()));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token Uazapi não configurado');

        $resolver->resolveProvider($instance);
    }

    public function test_resolve_provider_throws_when_provider_unsupported(): void
    {
        $instance = new ChatInstance([
            'provider' => 'unknown',
            'settings_json' => [],
        ]);

        $resolver = new ProviderResolver(new WhatsAppAdapterFactory(app()));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provedor não suportado');

        $resolver->resolveProvider($instance);
    }
}
