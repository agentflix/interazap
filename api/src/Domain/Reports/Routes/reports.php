<?php

declare(strict_types=1);

use Domain\Reports\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('reports')
    ->group(function (): void {
        Route::get('/sales-funnel', [ReportsController::class, 'salesFunnel']);
        Route::get('/revenue-sales', [ReportsController::class, 'revenueSales']);
        Route::get('/salesperson-performance', [ReportsController::class, 'salespersonPerformance']);
        Route::get('/loss-reasons', [ReportsController::class, 'lossReasons']);
        Route::get('/sla-resolution', [ReportsController::class, 'slaResolution']);
        Route::get('/agent-performance', [ReportsController::class, 'agentPerformance']);
        Route::get('/csat-nps', [ReportsController::class, 'csatNps']);
        Route::get('/chat-volume', [ReportsController::class, 'chatVolume']);
        Route::get('/ai-usage-cost', [ReportsController::class, 'aiUsageCost']);
        Route::get('/billing', [ReportsController::class, 'billing']);
        Route::get('/product-performance', [ReportsController::class, 'productPerformance']);
        Route::get('/autopilot-performance', [ReportsController::class, 'autopilotPerformance']);
        Route::get('/team-activity', [ReportsController::class, 'teamActivity']);
        Route::get('/contact-crm', [ReportsController::class, 'contactCrm']);

        Route::get('/{report}/export', [ReportsController::class, 'export'])
            ->where('report', '[a-z\-]+');
    });
