<?php

use Domain\Platform\Exceptions\PlanLimitExceededException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null;
if ($appEnv === 'testing') {
    $routesCachePath = dirname(__DIR__).'/bootstrap/cache/routes-v7-testing.php';
    $_ENV['APP_ROUTES_CACHE'] = $routesCachePath;
    $_SERVER['APP_ROUTES_CACHE'] = $routesCachePath;
}

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        App\Console\Commands\ConsumeChatStreamCommand::class,
        Domain\Billing\Console\Commands\BillingWebhookConsumer::class,
        App\Console\Commands\CloseInactiveTicketsCommand::class,
        App\Console\Commands\ChatSlaRecalcCommand::class,
        App\Console\Commands\ChatUazapiSendMediaCommand::class,
        Domain\Platform\Console\Commands\QueueHealthCommand::class,
        Domain\Platform\Console\Commands\TenantBootstrapDefaultsCommand::class,
        Domain\Platform\Console\Commands\ChatMessagesPartitionMaintenanceCommand::class,
        // FEAT-005: Trial management commands
        Domain\Billing\Console\Commands\CloseExpiredTrialsCommand::class,
        Domain\Billing\Console\Commands\SendTrialEndingSoonCommand::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        Domain\Shared\Providers\SharedServiceProvider::class,
        Laravel\Sanctum\SanctumServiceProvider::class,
        Spatie\Permission\PermissionServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware - applies to all routes
        $middleware->append(\Domain\Shared\Http\Middleware\SecurityHeadersMiddleware::class);
        $middleware->append(\Domain\Shared\Http\Middleware\TraceIdMiddleware::class);

        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->group('api', [
            \Domain\Shared\Http\Middleware\MetricsMiddleware::class,
            \Domain\Shared\Http\Middleware\TenantContextMiddleware::class,
            \Domain\Shared\Http\Middleware\LogAccessDeniedMiddleware::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->alias([
            'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \Illuminate\Auth\Middleware\RedirectIfAuthenticated::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'idempotent' => \Shared\Http\Middleware\IdempotentRequest::class,
            'tenant' => \Domain\Shared\Http\Middleware\TenantContextMiddleware::class,
            'billing.delinquency' => \Domain\Billing\Http\Middleware\BillingDelinquencyMiddleware::class,
            'internal.api.key' => \Domain\Shared\Http\Middleware\InternalApiKeyMiddleware::class,
            'gateway.secret' => \Domain\Chat\Http\Middleware\GatewaySecretGuard::class,
            'platform.super-admin' => \Domain\Platform\Http\Middleware\EnsurePlatformSuperAdmin::class,
            'force.password.change' => \Domain\Auth\Http\Middleware\ForcePasswordChangeMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PlanLimitExceededException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'PLAN_LIMIT_EXCEEDED',
            ], 403);
        });
    })->create();
