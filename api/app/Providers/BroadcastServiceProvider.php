<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

/**
 * Registro de canais de Broadcast.
 *
 * Responsável por publicar as rotas de autenticação e carregar os canais
 * definidos em routes/channels.php.
 */
final class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes([
            'prefix' => 'api',
            'middleware' => ['auth:sanctum'],
        ]);

        require base_path('routes/channels.php');
    }
}
