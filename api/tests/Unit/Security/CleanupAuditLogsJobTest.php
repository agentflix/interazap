<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use Domain\Shared\Jobs\CleanupAuditLogsJob;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class CleanupAuditLogsJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cleanup_deletes_logs_older_than_retention_period(): void
    {
        // Create old audit logs (100 days ago)
        $oldDate = \Illuminate\Support\Facades\Date::now()->subDays(100);

        DB::table('audit_logs')->insert([
            'id' => Str::orderedUuid()->toString(),
            'event' => 'test.old',
            'auditable_type' => 'App\\Models\\Test',
            'auditable_id' => Str::orderedUuid()->toString(),
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
        ]);

        // Create recent audit logs (10 days ago)
        $recentDate = \Illuminate\Support\Facades\Date::now()->subDays(10);

        DB::table('audit_logs')->insert([
            'id' => Str::orderedUuid()->toString(),
            'event' => 'test.recent',
            'auditable_type' => 'App\\Models\\Test',
            'auditable_id' => Str::orderedUuid()->toString(),
            'created_at' => $recentDate,
            'updated_at' => $recentDate,
        ]);

        $this->assertDatabaseCount('audit_logs', 2);

        // Run cleanup job with 90 days retention
        $job = new CleanupAuditLogsJob(90);
        $job->handle();

        // Old log should be deleted, recent should remain
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'test.recent',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'test.old',
        ]);
    }

    public function test_cleanup_respects_custom_retention_period(): void
    {
        // Create logs 45 days ago
        $date = \Illuminate\Support\Facades\Date::now()->subDays(45);

        DB::table('audit_logs')->insert([
            'id' => Str::orderedUuid()->toString(),
            'event' => 'test.45days',
            'auditable_type' => 'App\\Models\\Test',
            'auditable_id' => Str::orderedUuid()->toString(),
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        // With 90 days retention, log should NOT be deleted
        $job = new CleanupAuditLogsJob(90);
        $job->handle();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'test.45days',
        ]);

        // With 30 days retention, log SHOULD be deleted
        $job = new CleanupAuditLogsJob(30);
        $job->handle();

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'test.45days',
        ]);
    }

    public function test_cleanup_logs_deletion_count(): void
    {
        // Create multiple old audit logs
        $oldDate = \Illuminate\Support\Facades\Date::now()->subDays(100);

        for ($i = 0; $i < 5; $i++) {
            DB::table('audit_logs')->insert([
                'id' => Str::orderedUuid()->toString(),
                'event' => "test.old.{$i}",
                'auditable_type' => 'App\\Models\\Test',
                'auditable_id' => Str::orderedUuid()->toString(),
                'created_at' => $oldDate,
                'updated_at' => $oldDate,
            ]);
        }

        Log::shouldReceive('channel')
            ->with('audit')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'audit.cleanup.database'
                && $context['deleted_count'] === 5
                && $context['retention_days'] === 90
            );

        $job = new CleanupAuditLogsJob(90);
        $job->handle();
    }

    public function test_cleanup_does_not_log_when_nothing_deleted(): void
    {
        // Create only recent logs
        $recentDate = \Illuminate\Support\Facades\Date::now()->subDays(10);

        DB::table('audit_logs')->insert([
            'id' => Str::orderedUuid()->toString(),
            'event' => 'test.recent',
            'auditable_type' => 'App\\Models\\Test',
            'auditable_id' => Str::orderedUuid()->toString(),
            'created_at' => $recentDate,
            'updated_at' => $recentDate,
        ]);

        Log::shouldReceive('channel')
            ->with('audit')
            ->never();

        $job = new CleanupAuditLogsJob(90);
        $job->handle();

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_cleanup_deletes_old_security_log_files_and_keeps_recent_ones(): void
    {
        $logsPath = storage_path('logs');
        if (! is_dir($logsPath)) {
            mkdir($logsPath, 0777, true);
        }

        $oldFile = $logsPath.'/audit-2025-01-01.log';
        $recentFile = $logsPath.'/audit-2099-01-01.log';

        file_put_contents($oldFile, 'old');
        file_put_contents($recentFile, 'recent');

        Log::shouldReceive('channel')
            ->with('audit')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'audit.cleanup.files'
                && $context['deleted_count'] >= 1
                && $context['retention_days'] === 90
            );

        $job = new CleanupAuditLogsJob(90);
        $job->handle();

        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($recentFile);

        @unlink($recentFile);
    }

    public function test_cleanup_ignores_log_files_with_invalid_date_format(): void
    {
        $logsPath = storage_path('logs');
        if (! is_dir($logsPath)) {
            mkdir($logsPath, 0777, true);
        }

        $invalidDateFile = $logsPath.'/auth-invalid-date.log';
        file_put_contents($invalidDateFile, 'invalid');

        $job = new CleanupAuditLogsJob(90);
        $job->handle();

        $this->assertFileExists($invalidDateFile);

        @unlink($invalidDateFile);
    }

    public function test_cleanup_logs_warning_when_file_deletion_fails(): void
    {
        $logsPath = storage_path('logs');
        if (! is_dir($logsPath)) {
            mkdir($logsPath, 0777, true);
        }

        $failedPath = $logsPath.'/audit-2025-01-01.log';
        if (! is_dir($failedPath)) {
            mkdir($failedPath);
        }

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'audit.cleanup.file_delete_failed'
                && $context['file'] === $failedPath
                && $context['retention_days'] === 90
            );

        $job = new CleanupAuditLogsJob(90);
        $job->handle();

        $this->assertDirectoryExists($failedPath);
        rmdir($failedPath);
    }
}
