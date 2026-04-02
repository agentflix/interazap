<?php

declare(strict_types=1);

namespace App\Providers;

use Domain\Chat\Contracts\WhatsAppProviderPort;
use Domain\Shared\Infrastructure\WhatsApp\Adapters\FakeWhatsAppAdapter;
use Domain\Shared\Infrastructure\WhatsApp\Factories\WhatsAppAdapterFactory;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppAdapterFactory::class);

        $this->app->bind(WhatsAppProviderPort::class, function () {
            if ($this->app->environment('testing')) {
                return $this->app->make(FakeWhatsAppAdapter::class);
            }

            throw new RuntimeException(
                'WhatsAppProviderPort deve ser resolvido via ProviderResolver, não diretamente.'
            );
        });
    }
}
