<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Console;

use Domain\Ai\Console\Commands\PurgeAiUsageLogsCommand;
use Domain\Ai\Models\AiUsageLog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * @group ai
 * @group lgpd
 * @group console
 */
class PurgeAiUsageLogsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_registers_as_artisan_command(): void
    {
        $commands = Artisan::all();

        expect($commands)->toHaveKey('ai:purge-usage-logs');
        expect($commands['ai:purge-usage-logs'])->toBeInstanceOf(PurgeAiUsageLogsCommand::class);
    }

    public function test_it_deletes_logs_older_than_90_days(): void
    {
        // Create old logs (should be deleted)
        AiUsageLog::factory()->create(['created_at' => now()->subDays(91)]);
        AiUsageLog::factory()->create(['created_at' => now()->subDays(100)]);
        AiUsageLog::factory()->create(['created_at' => now()->subDays(365)]);

        // Create recent logs (should be kept)
        AiUsageLog::factory()->create(['created_at' => now()->subDays(30)]);
        AiUsageLog::factory()->create(['created_at' => now()->subDays(89)]);

        expect(\Domain\Ai\Models\AiUsageLog::query()->count())->toBe(5);

        Artisan::call('ai:purge-usage-logs');

        expect(\Domain\Ai\Models\AiUsageLog::query()->count())->toBe(2);
    }

    public function test_it_accepts_custom_retention_days(): void
    {
        AiUsageLog::factory()->create(['created_at' => now()->subDays(31)]);
        AiUsageLog::factory()->create(['created_at' => now()->subDays(29)]);

        Artisan::call('ai:purge-usage-logs', ['--days' => 30]);

        expect(\Domain\Ai\Models\AiUsageLog::query()->count())->toBe(1);
    }

    public function test_it_runs_in_dry_run_mode(): void
    {
        AiUsageLog::factory()->create(['created_at' => now()->subDays(100)]);
        AiUsageLog::factory()->create(['created_at' => now()->subDays(100)]);

        Artisan::call('ai:purge-usage-logs', ['--dry-run' => true]);

        // Logs should NOT be deleted in dry-run
        expect(\Domain\Ai\Models\AiUsageLog::query()->count())->toBe(2);
    }

    public function test_it_outputs_count_of_deleted_logs(): void
    {
        AiUsageLog::factory()->count(5)->create(['created_at' => now()->subDays(100)]);

        $exitCode = Artisan::call('ai:purge-usage-logs');
        $output = Artisan::output();

        expect($exitCode)->toBe(0);
        expect($output)->toContain('5');
    }

    public function test_it_handles_no_logs_to_delete(): void
    {
        AiUsageLog::factory()->create(['created_at' => now()->subDays(10)]);

        $exitCode = Artisan::call('ai:purge-usage-logs');
        Artisan::output();

        expect($exitCode)->toBe(0);
        expect(\Domain\Ai\Models\AiUsageLog::query()->count())->toBe(1);
    }

    public function test_it_deletes_in_chunks_for_performance(): void
    {
        // Create 150 old logs
        AiUsageLog::factory()->count(150)->create([
            'created_at' => now()->subDays(100),
        ]);

        Artisan::call('ai:purge-usage-logs', ['--chunk' => 50]);

        // All should be deleted despite chunking
        expect(\Domain\Ai\Models\AiUsageLog::query()->count())->toBe(0);
    }
}
