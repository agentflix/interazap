<?php

use Domain\Ai\Jobs\AutopilotApprovalExpiryJob;
use Illuminate\Support\Facades\Schedule;

Schedule::command('chat:close-inactive-tickets')->everyFiveMinutes();
Schedule::job(\Domain\Ai\Jobs\AutopilotZombieRunCleanupJob::class)->everyMinute();
Schedule::job(AutopilotApprovalExpiryJob::class)->hourly();
