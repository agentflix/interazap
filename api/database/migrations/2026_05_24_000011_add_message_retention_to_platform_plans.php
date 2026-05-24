<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds message_retention_days column to platform_plans table.
 *
 * NULL means indefinite retention (no automatic purging).
 * A positive integer value means messages older than N days will be
 * pruned by the ChatMessagesPartitionMaintenanceCommand.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('platform_plans', 'message_retention_days')) {
            return;
        }

        Schema::table('platform_plans', static function (Blueprint $table): void {
            $table->integer('message_retention_days')
                ->nullable()
                ->default(null)
                ->after('message_limit_monthly')
                ->comment('Days to retain chat messages. NULL = indefinite retention.');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('platform_plans', 'message_retention_days')) {
            return;
        }

        Schema::table('platform_plans', static function (Blueprint $table): void {
            $table->dropColumn('message_retention_days');
        });
    }
};
