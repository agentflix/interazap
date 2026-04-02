<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\BroadcastServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\TelescopeServiceProvider::class,
    App\Providers\WhatsAppServiceProvider::class,
    Domain\Ai\Providers\AiServiceProvider::class,
    Domain\Billing\Providers\BillingServiceProvider::class,
    Domain\Gateway\Providers\GatewayServiceProvider::class,
];
