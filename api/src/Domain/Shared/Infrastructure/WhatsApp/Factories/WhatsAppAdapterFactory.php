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
 * Factory para criação de adapters, conectores e normalizadores de provedores WhatsApp.
 *
 * Centraliza a instanciação de implementações concretas por provedor (uazapi),
 * substituindo por FakeWhatsAppAdapter em ambiente de testes quando configurado.
 */
final class WhatsAppAdapterFactory
{
    public function __construct(private readonly Container $container) {}

    /**
     * Cria o adapter de envio de mensagens para o provedor informado.
     *
     * Em ambiente de teste, retorna FakeWhatsAppAdapter quando não configurado
     * para usar o provedor real.
     *
     * @param  string  $provider  Nome do provedor (ex: 'uazapi').
     * @param  array<string, mixed>  $credentials  Credenciais de autenticação do provedor.
     * @return WhatsAppProviderPort Adapter concreto do provedor.
     *
     * @throws \InvalidArgumentException Se o provedor não for suportado.
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
     * Cria o conector de instância para o provedor informado.
     *
     * @param  string  $provider  Nome do provedor (ex: 'uazapi').
     * @param  array<string, mixed>  $credentials  Credenciais de autenticação do provedor.
     * @return InstanceConnectorPort Conector concreto do provedor.
     *
     * @throws \InvalidArgumentException Se o provedor não for suportado.
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

    /**
     * Cria o normalizador de webhook para o provedor informado.
     *
     * @param  string  $provider  Nome do provedor (ex: 'uazapi').
     * @return WebhookNormalizerPort Normalizador concreto do provedor.
     *
     * @throws \InvalidArgumentException Se o provedor não for suportado.
     */
    public function makeNormalizer(string $provider): WebhookNormalizerPort
    {
        return match ($provider) {
            'uazapi' => $this->container->make(UzapiWebhookNormalizer::class),
            default => throw new InvalidArgumentException("Provedor não suportado: {$provider}"),
        };
    }

    /**
     * Retorna a lista de provedores suportados pela factory.
     *
     * @return array<string> Nomes dos provedores suportados.
     */
    public function getSupportedProviders(): array
    {
        return ['uazapi'];
    }
}
