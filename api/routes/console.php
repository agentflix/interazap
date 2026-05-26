<?php

use Domain\Ai\Jobs\AutopilotApprovalExpiryJob;
use Domain\Billing\Jobs\CloseExpiredCyclesJob;
use Domain\Billing\Jobs\ReconcileFailedUsageJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('chat:close-inactive-tickets')->everyFiveMinutes();
Schedule::job(\Domain\Ai\Jobs\AutopilotZombieRunCleanupJob::class)->everyMinute();
Schedule::job(AutopilotApprovalExpiryJob::class)->hourly();
Schedule::job(new CloseExpiredCyclesJob)->dailyAt('03:00')->name('close-expired-cycles');
Schedule::job(new ReconcileFailedUsageJob)->dailyAt('04:00')->name('reconcile-failed-usage');

// Maintain chat_messages partitions and enforce message retention per plan
Schedule::command('chat:partitions:maintain')->dailyAt('02:00')->name('chat-partitions-maintain')->withoutOverlapping();

// Prune idempotency keys older than 90 days — prevents unbounded table growth
Schedule::call(function (): void {
    DB::table('ai_usage_idempotency_keys')
        ->where('created_at', '<', now()->subDays(90))
        ->delete();
})->dailyAt('05:00')->name('prune-ai-usage-idempotency-keys');

// FEAT-005: Trial management schedules
Schedule::command('billing:close-expired-trials')->dailyAt('03:00')->name('billing-close-expired-trials')->withoutOverlapping();
Schedule::command('billing:send-trial-ending-soon')->dailyAt('09:00')->name('billing-send-trial-ending-soon')->withoutOverlapping();
