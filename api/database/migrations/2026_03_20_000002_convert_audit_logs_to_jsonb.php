<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PLAN-004 Fase 2a — Convert audit_logs values to JSONB
 *
 * Converts old_values and new_values from TEXT to JSONB for direct querying.
 * Adds GIN indexes for efficient JSONB containment queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip if columns are already JSONB (schema was created with JSONB in unified migration)
        $columnType = DB::selectOne("SELECT data_type FROM information_schema.columns WHERE table_name = 'audit_logs' AND column_name = 'old_values'")?->data_type;
        if ($columnType === 'jsonb') {
            return;
        }

        DB::statement('
            CREATE OR REPLACE FUNCTION safe_text_to_jsonb(input TEXT)
            RETURNS JSONB AS
            $$
            BEGIN
                RETURN input::JSONB;
            EXCEPTION WHEN others THEN
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql IMMUTABLE
        ');

        DB::statement("
            ALTER TABLE audit_logs
                ALTER COLUMN old_values TYPE JSONB USING COALESCE(safe_text_to_jsonb(old_values), 'null'::JSONB),
                ALTER COLUMN new_values TYPE JSONB USING COALESCE(safe_text_to_jsonb(new_values), 'null'::JSONB)
        ");

        DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_logs_old_values_gin ON audit_logs USING GIN (old_values jsonb_path_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_logs_new_values_gin ON audit_logs USING GIN (new_values jsonb_path_ops)');

        DB::statement('DROP FUNCTION IF EXISTS safe_text_to_jsonb(TEXT)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_new_values_gin');
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_old_values_gin');

        DB::statement('
            ALTER TABLE audit_logs
                ALTER COLUMN old_values TYPE TEXT USING old_values::TEXT,
                ALTER COLUMN new_values TYPE TEXT USING new_values::TEXT
        ');

        DB::statement('DROP FUNCTION IF EXISTS safe_text_to_jsonb(TEXT)');
    }
};
