<?php

declare(strict_types=1);

namespace Domain\Gateway\Providers;

use Domain\Ai\Contracts\AIServiceInterface;
use Domain\Gateway\Contracts\GatewayClientInterface;
use Domain\Gateway\Enums\GatewayProvider;
use Domain\Gateway\Services\AI\AIGatewayService;
use Domain\Gateway\Services\RedisGatewayClient;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider do domínio Gateway.
 *
 * Registra GatewayClientInterface como singleton (RedisGatewayClient)
 * e AIServiceInterface como singleton (AIGatewayService) com configuração
 * de provider e timeout lidos de config/gateway.php.
 */
final class GatewayServiceProvider extends ServiceProvider
{
    /**
     * Registra os serviços do gateway no container de injeção de dependência.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            config_path('gateway.php'),
            'gateway'
        );

        // Register Gateway Client as singleton
        $this->app->singleton(
            GatewayClientInterface::class,
            RedisGatewayClient::class
        );

        // Register AI Service as singleton with configuration
        $this->app->singleton(AIServiceInterface::class, function ($app) {
            /** @var string $providerValue */
            $providerValue = config('gateway.ai.default_provider', 'openai');

            return new AIGatewayService(
                client: $app->make(GatewayClientInterface::class),
                defaultProvider: GatewayProvider::tryFrom($providerValue) ?? GatewayProvider::OPENAI,
                timeoutSeconds: (int) config('gateway.ai.timeout_seconds', 180),
            );
        });
    }

    /**
     * Inicializa o provider publicando o arquivo de configuração do gateway.
     */
    public function boot(): void
    {
        // Publish config if needed
        $this->publishes([
            __DIR__.'/../../../../config/gateway.php' => config_path('gateway.php'),
        ], 'gateway-config');
    }
}
