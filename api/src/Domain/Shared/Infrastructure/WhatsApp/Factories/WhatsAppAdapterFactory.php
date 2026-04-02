<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\WhatsApp\Factories;

use Domain\Chat\Contracts\InstanceConnectorPort;
use Domain\Chat\Contracts\WebhookNormalizerPort;
use Domain\Chat\Contracts\WhatsAppProviderPort;
use Domain\Shared\Infrastructure\WhatsApp\Adapters\FakeWhatsAppAdapter;
use Domain\Shared\Infrastructure\WhatsApp\Adapters\UzapiAdapter;
use Domain\Shared\Infrastructure\WhatsApp\Connectors\UzapiConnector;
use Domain\Shared\Infrastructure\WhatsApp\Normalizers\UzapiWebhookNormalizer;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Factory para criação de adapters de provedores WhatsApp.
 */
final class WhatsAppAdapterFactory
{
    public function __construct(private readonly Container $container) {}

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function makeProvider(string $provider, array $credentials): WhatsAppProviderPort
    {
        if (app()->environment('testing') && ! config('services.whatsapp.use_real_provider')) {
            return $this->container->make(FakeWhatsAppAdapter::class);
        }

        return match ($provider) {
            'uazapi' => new UzapiAdapter(
                token: (string) $credentials['token'],
                baseUrl: (string) ($credentials['base_url'] ?? config('services.uazapi.base_url')),
            ),
            default => throw new InvalidArgumentException("Provedor não suportado: {$provider}"),
        };
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function makeConnector(string $provider, array $credentials): InstanceConnectorPort
    {
        return match ($provider) {
            'uazapi' => $this->container->make(UzapiConnector::class, [
                'token' => (string) $credentials['token'],
            ]),
            default => throw new InvalidArgumentException("Provedor não suportado: {$provider}"),
        };
    }

    public function makeNormalizer(string $provider): WebhookNormalizerPort
    {
        return match ($provider) {
            'uazapi' => $this->container->make(UzapiWebhookNormalizer::class),
            default => throw new InvalidArgumentException("Provedor não suportado: {$provider}"),
        };
    }

    /**
     * @return array<string>
     */
    public function getSupportedProviders(): array
    {
        return ['uazapi'];
    }
}
