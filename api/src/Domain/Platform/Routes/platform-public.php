<?php

declare(strict_types=1);

use Domain\Platform\Http\Controllers\PlatformLeadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform Public Routes
|--------------------------------------------------------------------------
| Rotas públicas (sem `auth:sanctum`) do domínio Platform.
| Devem ser registradas dentro do grupo `throttle:public` em `routes/api.php`.
|
| Atualmente expõe apenas a captura de leads vinda da landing page.
*/

Route::post('/public/leads', [PlatformLeadController::class, 'store']);
