<?php

declare(strict_types=1);

use Domain\Shared\Http\Controllers\GlobalSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])
    ->group(function (): void {
        Route::get('/search', GlobalSearchController::class)->name('search.global');
    });
