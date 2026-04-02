<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Configure notifications for failed jobs (OBS-CRIT-001)
        // Set these in config/horizon.php via env vars

        if ($email = config('horizon.notifications.email')) {
            Horizon::routeMailNotificationsTo($email);
        }

        if ($slack = config('horizon.notifications.slack.webhook')) {
            $channel = config('horizon.notifications.slack.channel', '#alerts');
            Horizon::routeSlackNotificationsTo($slack, $channel);
        }
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null): bool => $user?->email == config('horizon.admin_email'));
    }
}
