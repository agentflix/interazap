<?php

declare(strict_types=1);

use Domain\Platform\Http\Controllers\PlatformBillingInvoiceController;
use Domain\Platform\Http\Controllers\PlatformLeadAdminController;
use Domain\Platform\Http\Controllers\PlatformPlanController;
use Domain\Platform\Http\Controllers\PlatformTenantController;
use Domain\Platform\Http\Controllers\PlatformUazapiInstanceController;
use Domain\Platform\Http\Controllers\PlatformUazapiMessageController;
use Domain\Platform\Http\Controllers\PlatformUserController;
use Domain\Platform\Http\Controllers\QueueAdminController;
use Domain\Platform\Http\Controllers\QueueHealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Queue Health Routes (Internal Monitoring)
|--------------------------------------------------------------------------
| These routes are used for internal queue monitoring and health checks.
| Protected by throttle middleware to prevent abuse.
*/
Route::middleware(['throttle:observability'])
    ->prefix('health')
    ->group(function (): void {
        Route::get('/queues', [QueueHealthController::class, 'index']);
        Route::get('/queue', [QueueHealthController::class, 'index']);
        Route::get('/queues/config', [QueueHealthController::class, 'config']);
        Route::get('/queues/{queue}', [QueueHealthController::class, 'show']);
    });

Route::middleware(['auth:sanctum'])
    ->prefix('admin/queues')
    ->group(function (): void {
        Route::get('/', [QueueAdminController::class, 'index']);
        Route::get('/{name}', [QueueAdminController::class, 'show']);
        Route::post('/{name}/pause', [QueueAdminController::class, 'pause']);
        Route::post('/{name}/resume', [QueueAdminController::class, 'resume']);
        Route::post('/{name}/clean', [QueueAdminController::class, 'clean']);

        Route::get('/dlq', [QueueAdminController::class, 'deadLetterIndex']);
        Route::post('/dlq/{id}/retry', [QueueAdminController::class, 'deadLetterRetry']);
        Route::post('/dlq/retry-all', [QueueAdminController::class, 'deadLetterRetryAll']);
        Route::delete('/dlq/{id}', [QueueAdminController::class, 'deadLetterPurge']);
        Route::post('/dlq/purge-all', [QueueAdminController::class, 'deadLetterPurgeAll']);

        Route::get('/circuits', [QueueAdminController::class, 'circuitsIndex']);
        Route::get('/circuits/{name}', [QueueAdminController::class, 'circuitsShow']);
        Route::post('/circuits/{name}/reset', [QueueAdminController::class, 'circuitsReset']);
        Route::post('/circuits/{name}/open', [QueueAdminController::class, 'circuitsOpen']);
    });

Route::middleware(['auth:sanctum'])
    ->prefix('platform')
    ->group(function (): void {
        Route::get('/tenants', [PlatformTenantController::class, 'index']);
        Route::post('/tenants', [PlatformTenantController::class, 'store']);
        Route::get('/tenants/export', [PlatformTenantController::class, 'export']);
        Route::get('/tenants/{id}', [PlatformTenantController::class, 'show']);
        Route::get('/tenants/{id}/details', [PlatformTenantController::class, 'details']);
        Route::put('/tenants/{id}', [PlatformTenantController::class, 'update']);
        Route::delete('/tenants/{id}', [PlatformTenantController::class, 'destroy']);
        Route::patch('/tenants/{id}/toggle-active', [PlatformTenantController::class, 'toggleActive']);
        Route::post('/tenants/{id}/restore', [PlatformTenantController::class, 'restore']);
        Route::delete('/tenants/{id}/force', [PlatformTenantController::class, 'forceDelete']);
        Route::delete('/tenants/{id}/purge', [PlatformTenantController::class, 'purge']);
        Route::post('/tenants/{id}/impersonate', [PlatformTenantController::class, 'impersonate']);
        Route::get('/tenants/{id}/settings', [PlatformTenantController::class, 'settings']);
        Route::patch('/tenants/{id}/settings', [PlatformTenantController::class, 'updateSettings']);

        Route::get('/plans/validate-slug', [PlatformPlanController::class, 'validateSlug']);
        Route::get('/plans', [PlatformPlanController::class, 'index']);
        Route::post('/plans', [PlatformPlanController::class, 'store']);
        Route::get('/plans/{id}', [PlatformPlanController::class, 'show']);
        Route::put('/plans/{id}', [PlatformPlanController::class, 'update']);
        Route::delete('/plans/{id}', [PlatformPlanController::class, 'destroy']);
        Route::patch('/plans/{id}/toggle', [PlatformPlanController::class, 'toggle']);

        Route::get('/leads', [PlatformLeadAdminController::class, 'index']);
        Route::get('/leads/export', [PlatformLeadAdminController::class, 'export'])->name('platform.leads.export');
        Route::post('/leads/{id}/convert', [PlatformLeadAdminController::class, 'convert'])->name('platform.leads.convert');

        Route::get('/uazapi/instances', [PlatformUazapiInstanceController::class, 'index']);
        Route::post('/uazapi/instances', [PlatformUazapiInstanceController::class, 'store']);
        Route::get('/uazapi/instances/{id}', [PlatformUazapiInstanceController::class, 'show']);
        Route::get('/uazapi/instances/{id}/status', [PlatformUazapiInstanceController::class, 'status']);
        Route::post('/uazapi/instances/{id}/connect', [PlatformUazapiInstanceController::class, 'connect']);
        Route::post('/uazapi/instances/{id}/disconnect', [PlatformUazapiInstanceController::class, 'disconnect']);
        Route::patch('/uazapi/instances/{id}/admin-fields', [PlatformUazapiInstanceController::class, 'updateAdminFields']);
        Route::patch('/uazapi/instances/{id}/name', [PlatformUazapiInstanceController::class, 'updateName']);
        Route::post('/uazapi/instances/{id}/profile-image', [PlatformUazapiInstanceController::class, 'profileImage']);
        Route::post('/uazapi/instances/{id}/presence', [PlatformUazapiInstanceController::class, 'presence']);
        Route::delete('/uazapi/instances/{id}', [PlatformUazapiInstanceController::class, 'destroy']);

        Route::post('/uazapi/instances/{token}/messages/text', [PlatformUazapiMessageController::class, 'sendText']);
        Route::post('/uazapi/instances/{token}/messages/file', [PlatformUazapiMessageController::class, 'sendFile']);

        // Platform Billing Invoices (admin)
        Route::get('/billing/invoices', [PlatformBillingInvoiceController::class, 'index']);
        Route::post('/billing/invoices', [PlatformBillingInvoiceController::class, 'store']);
        Route::delete('/billing/invoices/{id}', [PlatformBillingInvoiceController::class, 'destroy']);

        // Platform Users (all tenants - super-admin only)
        Route::get('/users', [PlatformUserController::class, 'index']);
        Route::post('/users', [PlatformUserController::class, 'store']);
        Route::get('/users/{id}', [PlatformUserController::class, 'show']);
        Route::put('/users/{id}', [PlatformUserController::class, 'update']);
        Route::delete('/users/{id}', [PlatformUserController::class, 'destroy']);
        Route::post('/users/{id}/toggle', [PlatformUserController::class, 'toggle']);
        Route::post('/users/{id}/avatar', [PlatformUserController::class, 'uploadAvatar']);
        Route::delete('/users/{id}/avatar', [PlatformUserController::class, 'deleteAvatar']);
        Route::post('/users/{id}/impersonate', [PlatformUserController::class, 'impersonate']);
    });
