<?php

declare(strict_types=1);

namespace Domain\Platform\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ChatMessagesPartitionMaintenanceCommand extends Command
{
    protected $signature = 'chat:partitions:maintain {--dry-run} {--months-ahead=3}';

    protected $description = 'Cria partições mensais futuras de chat_messages e descarta antigas via DROP PARTITION';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $monthsAhead = (int) $this->option('months-ahead');

        if ($dryRun) {
            $this->info('[DRY-RUN] Nenhuma alteração será aplicada.');
        }

        $this->ensureFuturePartitions($monthsAhead, $dryRun);
        $this->dropOldPartitions($dryRun);

        return self::SUCCESS;
    }

    private function ensureFuturePartitions(int $monthsAhead, bool $dryRun): void
    {
        $start = Carbon::now()->startOfMonth();

        for ($i = 0; $i <= $monthsAhead; $i++) {
            $from = $start->copy()->addMonths($i);
            $to = $from->copy()->addMonth();
            $name = 'chat_messages_'.$from->format('Y_m');

            $exists = DB::selectOne(
                'SELECT 1 FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE c.relname = ? AND n.nspname = current_schema()',
                [$name]
            );

            if ($exists !== null) {
                continue;
            }

            $this->info("[CREATE] {$name}");

            if (! $dryRun) {
                DB::statement("
                    CREATE TABLE IF NOT EXISTS {$name} PARTITION OF chat_messages
                    FOR VALUES FROM ('{$from->toDateTimeString()}') TO ('{$to->toDateTimeString()}')
                ");
            }
        }
    }

    private function dropOldPartitions(bool $dryRun): void
    {
        // Global cutoff = largest retention among all plans (most conservative)
        // Only DROP a partition when ALL tenants — including those on longest retention — have passed it
        $maxRetentionDays = (int) DB::table('platform_plans')
            ->where('message_retention_days', '>', 0)
            ->whereNotNull('message_retention_days')
            ->max('message_retention_days');

        if ($maxRetentionDays === 0) {
            $this->warn('Nenhum plano com message_retention_days > 0. DROP pulado.');

            return;
        }

        $cutoff = Carbon::now()->subDays($maxRetentionDays)->startOfMonth();

        // List only monthly partitions (pattern: chat_messages_YYYY_MM)
        $partitions = DB::select("
            SELECT child.relname AS partition_name
            FROM pg_inherits
            JOIN pg_class parent ON pg_inherits.inhparent = parent.oid
            JOIN pg_class child  ON pg_inherits.inhrelid  = child.oid
            WHERE parent.relname = 'chat_messages'
              AND child.relname  ~ '^chat_messages_[0-9]{4}_[0-9]{2}$'
        ");

        foreach ($partitions as $row) {
            if (! preg_match('/chat_messages_(\d{4})_(\d{2})/', $row->partition_name, $m)) {
                continue;
            }

            $partitionEnd = Carbon::create((int) $m[1], (int) $m[2], 1)->addMonth();

            if ($partitionEnd->lessThanOrEqualTo($cutoff)) {
                $this->info("[DROP] {$row->partition_name} (cutoff={$cutoff->toDateString()})");

                if (! $dryRun) {
                    DB::statement("DROP TABLE IF EXISTS {$row->partition_name}");
                }
            }
        }
    }
}
