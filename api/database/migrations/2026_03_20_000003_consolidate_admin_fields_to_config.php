<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-004 Fase 2b — Consolidate admin_field_0X into JSONB config
 *
 * Replaces admin_field_01 and admin_field_02 with a structured JSONB config column.
 * Migrates existing data before dropping old columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_uazapi_instances', function (Blueprint $table): void {
            $table->jsonb('config')->default('{}')->after('webhook_url');
        });

        DB::statement("
            UPDATE platform_uazapi_instances
            SET config = jsonb_build_object(
                'admin_field_01', COALESCE(admin_field_01, ''),
                'admin_field_02', COALESCE(admin_field_02, '')
            )
            WHERE admin_field_01 IS NOT NULL OR admin_field_02 IS NOT NULL
        ");

        Schema::table('platform_uazapi_instances', function (Blueprint $table): void {
            $table->dropColumn(['admin_field_01', 'admin_field_02']);
        });
    }

    public function down(): void
    {
        Schema::table('platform_uazapi_instances', function (Blueprint $table): void {
            $table->string('admin_field_01')->nullable()->after('webhook_url');
            $table->string('admin_field_02')->nullable()->after('admin_field_01');
        });

        DB::statement("
            UPDATE platform_uazapi_instances
            SET admin_field_01 = config->>'admin_field_01',
                admin_field_02 = config->>'admin_field_02'
            WHERE config IS NOT NULL AND config != '{}'::JSONB
        ");

        Schema::table('platform_uazapi_instances', function (Blueprint $table): void {
            $table->dropColumn('config');
        });
    }
};
