<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-004 Fase 1 — Database Utilities & Missing Indexes
 *
 * - View v_table_stats for monitoring table sizes
 * - Function cleanup_audit_logs for data retention
 * - Index idx_auth_users_email for fast email lookup
 * - Index idx_auth_personal_access_tokens_tokenable for polymorphic token lookup
 * - Index idx_ai_usage_logs_tenant_feature for feature-based queries
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // VIEW: v_table_stats — Table size monitoring
        // =====================================================================
        DB::statement('
            CREATE OR REPLACE VIEW v_table_stats AS
            SELECT
                schemaname,
                relname AS table_name,
                n_live_tup AS row_count,
                n_dead_tup AS dead_rows,
                pg_size_pretty(pg_total_relation_size(relid)) AS total_size,
                pg_total_relation_size(relid) AS total_size_bytes
            FROM pg_stat_user_tables
            ORDER BY pg_total_relation_size(relid) DESC
        ');

        // =====================================================================
        // FUNCTION: cleanup_audit_logs — Data retention (default 12 months)
        // =====================================================================
        DB::statement("
            CREATE OR REPLACE FUNCTION cleanup_audit_logs(months_to_keep INTEGER DEFAULT 12)
            RETURNS INTEGER AS \$\$
            DECLARE
                deleted_count INTEGER;
            BEGIN
                DELETE FROM audit_logs
                WHERE created_at < NOW() - (months_to_keep || ' months')::INTERVAL;

                GET DIAGNOSTICS deleted_count = ROW_COUNT;
                RETURN deleted_count;
            END;
            \$\$ LANGUAGE plpgsql
        ");

        // =====================================================================
        // INDEX: idx_auth_users_email — Fast email-only lookup
        // =====================================================================
        if (! $this->indexExists('auth_users', 'idx_auth_users_email')) {
            Schema::table('auth_users', function ($table) {
                $table->index('email', 'idx_auth_users_email');
            });
        }

        // =====================================================================
        // INDEX: idx_auth_personal_access_tokens_tokenable — Token owner lookup
        // =====================================================================
        if (! $this->indexExists('auth_personal_access_tokens', 'idx_auth_personal_access_tokens_tokenable')) {
            Schema::table('auth_personal_access_tokens', function ($table) {
                $table->index(['tokenable_type', 'tokenable_id'], 'idx_auth_personal_access_tokens_tokenable');
            });
        }

        // =====================================================================
        // INDEX: idx_ai_usage_logs_tenant_feature — Feature-based queries
        // =====================================================================
        if (! $this->indexExists('ai_usage_logs', 'idx_ai_usage_logs_tenant_feature')) {
            Schema::table('ai_usage_logs', function ($table) {
                $table->index(['tenant_id', 'feature', 'created_at'], 'idx_ai_usage_logs_tenant_feature');
            });
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_ai_usage_logs_tenant_feature');
        DB::statement('DROP INDEX IF EXISTS idx_auth_personal_access_tokens_tokenable');
        DB::statement('DROP INDEX IF EXISTS idx_auth_users_email');
        DB::statement('DROP FUNCTION IF EXISTS cleanup_audit_logs(INTEGER)');
        DB::statement('DROP VIEW IF EXISTS v_table_stats');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::selectOne(
            'SELECT EXISTS (SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?) AS exists',
            [$table, $indexName]
        );

        return (bool) ($result->exists ?? false);
    }
};
