<?php

declare(strict_types=1);

namespace Domain\Ai\Listeners;

use Domain\Ai\Events\AiBudgetThresholdExceeded;
use Domain\Ai\Notifications\AiBudgetThresholdNotification;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sends budget threshold notifications via e-mail and in-app channels.
 */
final class AiBudgetThresholdNotificationListener
{
    /**
     * Handle the event.
     */
    public function handle(AiBudgetThresholdExceeded $event): void
    {
        /** @var Collection<int, AuthUser> $recipients */
        $recipients = AuthUser::query()
            ->where(function ($query) use ($event): void {
                $query->where('tenant_id', $event->tenantId)
                    ->whereHas('roles', static function ($roleQuery): void {
                        $roleQuery->where('guard_name', 'sanctum')
                            ->whereIn('name', ['admin', 'owner']);
                    });
            })
            ->orWhereHas('roles', static function ($roleQuery): void {
                $roleQuery->where('guard_name', 'sanctum')
                    ->where('name', AuthRole::SUPER_ADMIN);
            })
            ->get();

        if ($recipients->isEmpty()) {
            Log::warning('No recipients found for AI budget threshold notification', [
                'tenant_id' => $event->tenantId,
                'period' => $event->period,
                'level' => $event->level,
            ]);

            return;
        }

        Notification::send($recipients, new AiBudgetThresholdNotification($event));
    }
}
