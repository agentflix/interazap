<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add reports_mode column to platform_plans
 *
 * This column defines the level of report access for each subscription plan:
 * - BASIC:   Limited reports (e.g. chat volume)
 * - ADVANCED: Extended reports (chat, CRM, AI sentiment)
 * - FULL:    All reports including export
 *
 * Rollback drops the column and the PostgreSQL enum type.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create PostgreSQL ENUM type if not exists
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'platform_reports_mode') THEN
                    CREATE TYPE platform_reports_mode AS ENUM ('BASIC', 'ADVANCED', 'FULL');
                END IF;
            END
            $$
        ");

        // Add column as PostgreSQL ENUM type with default 'BASIC' (appended at end)
        DB::statement("
            ALTER TABLE platform_plans
            ADD COLUMN reports_mode platform_reports_mode NOT NULL DEFAULT 'BASIC'
        ");

        Schema::table('platform_plans', function ($table): void {
            $table->index('reports_mode', 'platform_plans_reports_mode_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_plans', function ($table): void {
            $table->dropIndex('platform_plans_reports_mode_index');
            $table->dropColumn('reports_mode');
        });

        DB::statement('DROP TYPE IF EXISTS platform_reports_mode');
    }
};
