<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check Route (zero external dependencies)
|--------------------------------------------------------------------------
| Must work even when Redis/cache is down, so it runs without the api
| middleware group and without throttle (both depend on Redis).
| Security: endpoint only returns service status, no sensitive data.
*/
Route::withoutMiddleware('api')->group(function (): void {
    Route::get('/health', \Domain\Shared\Http\Controllers\HealthController::class);
});

/*
|--------------------------------------------------------------------------
| Metrics Route (SEC-002: Protected with throttle)
|--------------------------------------------------------------------------
| Rate limited to prevent DoS and information disclosure.
*/
Route::middleware(['throttle:observability'])->group(function (): void {
    Route::get('/metrics', \Domain\Shared\Http\Controllers\MetricsController::class);
});

Route::middleware(['api', 'throttle:api', 'billing.delinquency'])->group(function (): void {
    require base_path('src/Domain/Ai/Routes/ai.php');
    require base_path('src/Domain/Ai/Routes/ai-knowledge.php');
    require base_path('src/Domain/Ai/Routes/ai-usage.php');
    require base_path('src/Domain/Ai/Routes/ai-notifications.php');
    require base_path('src/Domain/Shared/Routes/search.php');
    require base_path('src/Domain/Auth/Routes/auth.php');
    require base_path('src/Domain/CRM/Routes/crm.php');
    require base_path('src/Domain/Chat/Routes/chat.php');
    require base_path('src/Domain/Dashboard/Routes/dashboard.php');
    require base_path('src/Domain/Billing/Routes/billing.php');
    require base_path('src/Domain/Platform/Routes/platform.php');
    require base_path('src/Domain/Configuration/Routes/configuration.php');
    require base_path('src/Domain/Reports/Routes/reports.php');
});

/*
|--------------------------------------------------------------------------
| Public Routes with Rate Limiting (SEC-001)
|--------------------------------------------------------------------------
| These routes are publicly accessible but protected with rate limiting
| to prevent brute force attacks and DoS attempts.
*/
Route::middleware(['throttle:public'])->group(function (): void {
    Route::get('/utils/cnpj/{cnpj}', \Domain\Shared\Http\Controllers\CnpjLookupController::class);
    Route::get('/utils/cep/{cep}', \Domain\Shared\Http\Controllers\CepLookupController::class);
    Route::get('/crm/cnpj/{cnpj}', \Domain\Shared\Http\Controllers\CnpjLookupController::class);
});
