<?php

declare(strict_types=1);

use Domain\Billing\Http\Controllers\BillingAdminDelinquencyController;
use Domain\Billing\Http\Controllers\BillingInvoiceController;
use Domain\Billing\Http\Controllers\BillingInvoiceReceiptController;
use Domain\Billing\Http\Controllers\BillingPrefsController;
use Domain\Billing\Http\Controllers\BillingSubscriptionController;
use Domain\Billing\Http\Controllers\BillingUsageController;
use Domain\Billing\Http\Controllers\Internal\InternalBillingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'billing.delinquency'])
    ->prefix('billing')
    ->group(function (): void {
        Route::get('/invoices', [BillingInvoiceController::class, 'index']);
        Route::post('/invoices', [BillingInvoiceController::class, 'store']);
        Route::get('/invoices/{id}', [BillingInvoiceController::class, 'show']);
        Route::put('/invoices/{id}', [BillingInvoiceController::class, 'update']);
        Route::delete('/invoices/{id}', [BillingInvoiceController::class, 'destroy']);
        Route::post('/invoices/{id}/pay', [BillingInvoiceController::class, 'pay']);
        Route::get('/invoices/{id}/pix', [BillingInvoiceController::class, 'pix']);
        Route::get('/invoices/{id}/receipt', [BillingInvoiceReceiptController::class, 'show']);

        Route::get('/subscription', [BillingSubscriptionController::class, 'subscription']);
        Route::get('/plans', [BillingSubscriptionController::class, 'plans']);
        Route::get('/plan-change/preview', [BillingSubscriptionController::class, 'preview']);
        Route::post('/plan-change', [BillingSubscriptionController::class, 'change']);
    });

Route::middleware(['auth:sanctum'])
    ->prefix('admin/billing/delinquent')
    ->group(function (): void {
        Route::get('/', [BillingAdminDelinquencyController::class, 'index']);
        Route::post('/{tenantId}/unlock', [BillingAdminDelinquencyController::class, 'unlock']);
    });

Route::middleware(['internal.api.key'])
    ->prefix('billing/usage')
    ->group(function (): void {
        Route::post('/check-and-increment', [BillingUsageController::class, 'check']);
        Route::post('/fail-open-log', [BillingUsageController::class, 'logFailure']);
    });

Route::middleware(['auth:sanctum'])
    ->group(function (): void {
        Route::patch('/tenants/me/billing-prefs', [BillingPrefsController::class, 'update']);
    });

Route::middleware(['internal.api.key'])
    ->prefix('internal/billing')
    ->group(function (): void {
        Route::get('tenants/by-webhook-token/{token}', [InternalBillingController::class, 'tenantByWebhookToken']);
        Route::post('webhook-events', [InternalBillingController::class, 'createWebhookEvent']);
        Route::patch('webhook-events/{eventId}/stream-id', [InternalBillingController::class, 'updateStreamId']);
    });
