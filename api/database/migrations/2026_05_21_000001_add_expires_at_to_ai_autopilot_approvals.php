<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_autopilot_approvals', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_autopilot_approvals', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
                $table->index('expires_at', 'idx_ai_autopilot_approvals_expires_at');
            }

            if (! Schema::hasColumn('ai_autopilot_approvals', 'expired_at')) {
                $table->timestamp('expired_at')->nullable();
            }
        });

        DB::statement(<<<'SQL'
            UPDATE ai_autopilot_approvals
            SET    expires_at = LEAST(created_at + INTERVAL '24 hours', NOW())
            WHERE  expires_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('ai_autopilot_approvals', function (Blueprint $table): void {
            if (Schema::hasColumn('ai_autopilot_approvals', 'expires_at')) {
                $table->dropIndex('idx_ai_autopilot_approvals_expires_at');
                $table->dropColumn('expires_at');
            }

            if (Schema::hasColumn('ai_autopilot_approvals', 'expired_at')) {
                $table->dropColumn('expired_at');
            }
        });
    }
};
