<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Contracts\InstanceConnectorPort;
use Domain\Chat\Contracts\WhatsAppProviderPort;
use Domain\Chat\Models\ChatInstance;
use Domain\Shared\Infrastructure\WhatsApp\Factories\WhatsAppAdapterFactory;
use InvalidArgumentException;

/**
 * Resolve o adapter correto baseado na instância.
 */
/**
 * @deprecated Dead code per API audit. Keep only as reference until provider factory refactor.
 */
final class ProviderResolver
{
    public function __construct(
        private readonly WhatsAppAdapterFactory $factory,
    ) {}

    public function resolveProvider(ChatInstance $instance): WhatsAppProviderPort
    {
        $credentials = $this->extractCredentials($instance);

        return $this->factory->makeProvider(
            provider: $instance->provider,
            credentials: $credentials,
        );
    }

    public function resolveConnector(ChatInstance $instance): InstanceConnectorPort
    {
        $credentials = $this->extractCredentials($instance);

        return $this->factory->makeConnector(
            provider: $instance->provider,
            credentials: $credentials,
        );
    }

    /**
     * @return array{token?: string, instance_id?: string, token_id?: string, client_token?: string, base_url?: string}
     */
    private function extractCredentials(ChatInstance $instance): array
    {
        $settings = $instance->settings_json ?? [];

        return match ($instance->provider) {
            'uazapi' => [
                'token' => $settings['token'] ?? throw new InvalidArgumentException('Token Uazapi não configurado'),
                'base_url' => $settings['base_url'] ?? config('services.uazapi.base_url'),
            ],
            default => throw new InvalidArgumentException("Provedor não suportado: {$instance->provider}"),
        };
    }
}
