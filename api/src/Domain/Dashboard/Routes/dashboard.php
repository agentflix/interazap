<?php

declare(strict_types=1);

use Domain\Dashboard\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/dashboard', [DashboardController::class, 'index']);
