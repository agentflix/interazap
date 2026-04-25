<?php

return array_filter([
    App\Providers\AppServiceProvider::class,
    App\Providers\BroadcastServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)
        ? App\Providers\TelescopeServiceProvider::class
        : null,
    App\Providers\WhatsAppServiceProvider::class,
    Domain\Ai\Providers\AiServiceProvider::class,
    Domain\Billing\Providers\BillingServiceProvider::class,
    Domain\Gateway\Providers\GatewayServiceProvider::class,
]);
