<?php

declare(strict_types=1);

namespace Domain\Shared\Jobs;

use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SplFileInfo;

/**
 * Job para limpeza de logs de auditoria antigos.
 *
 * Remove registros de audit_logs e arquivos de log do sistema
 * que excedam o período de retenção configurado (padrão: 90 dias).
 *
 * @category Jobs
 */
final class CleanupAuditLogsJob implements ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const DEFAULT_RETENTION_DAYS = 90;

    public function __construct(
        private readonly int $retentionDays = self::DEFAULT_RETENTION_DAYS,
    ) {}

    /**
     * Executa a limpeza de logs de auditoria no banco e em arquivos.
     */
    public function handle(): void
    {
        $this->cleanupDatabaseLogs();
        $this->cleanupFileLogs();
    }

    /**
     * Remove registros antigos da tabela audit_logs no banco de dados.
     */
    private function cleanupDatabaseLogs(): void
    {
        $cutoffDate = Carbon::now()->subDays($this->retentionDays);

        $deleted = DB::table('audit_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        if ($deleted > 0) {
            Log::channel('audit')->info('audit.cleanup.database', [
                'deleted_count' => $deleted,
                'retention_days' => $this->retentionDays,
                'cutoff_date' => $cutoffDate->toDateTimeString(),
            ]);
        }
    }

    /**
     * Remove arquivos de log antigos no diretório storage/logs.
     *
     * Processa padrões auth-*, access-denied-*, security-* e audit-*,
     * excluindo arquivos com data de nome anterior ao período de retenção.
     */
    private function cleanupFileLogs(): void
    {
        $logsPath = storage_path('logs');

        if (! is_dir($logsPath)) {
            return;
        }

        $cutoffDate = Carbon::now()->subDays($this->retentionDays);
        $deletedFiles = 0;

        $logPatterns = [
            'auth-*.log',
            'access-denied-*.log',
            'security-*.log',
            'audit-*.log',
        ];

        foreach ($logPatterns as $pattern) {
            $files = glob($logsPath.'/'.$pattern);

            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                $fileInfo = new SplFileInfo($file);

                // Extract date from filename (e.g., auth-2024-01-15.log)
                if (preg_match('/-(\d{4}-\d{2}-\d{2})\.log$/', $fileInfo->getFilename(), $matches)) {
                    try {
                        $fileDate = Carbon::createFromFormat('Y-m-d', $matches[1]);

                        if ($fileDate && $fileDate->isBefore($cutoffDate)) {
                            set_error_handler(static fn (): bool => true);
                            $deleted = unlink($file);
                            restore_error_handler();

                            if ($deleted) {
                                $deletedFiles++;
                            } else {
                                Log::warning('audit.cleanup.file_delete_failed', [
                                    'file' => $file,
                                    'retention_days' => $this->retentionDays,
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        // Skip files with invalid date format
                        continue;
                    }
                }
            }
        }

        if ($deletedFiles > 0) {
            Log::channel('audit')->info('audit.cleanup.files', [
                'deleted_count' => $deletedFiles,
                'retention_days' => $this->retentionDays,
                'cutoff_date' => $cutoffDate->toDateTimeString(),
            ]);
        }
    }
}
