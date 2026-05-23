<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('ai_autopilot_actions', 'tenant_id')) {
            return;
        }

        Schema::table('ai_autopilot_actions', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->after('id');
        });

        DB::statement('UPDATE ai_autopilot_actions AS actions
            SET tenant_id = runs.tenant_id
            FROM ai_autopilot_runs AS runs
            WHERE actions.run_id = runs.id');

        DB::statement('ALTER TABLE ai_autopilot_actions ALTER COLUMN tenant_id SET NOT NULL');

        Schema::table('ai_autopilot_actions', function (Blueprint $table): void {
            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'run_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ai_autopilot_actions') || ! Schema::hasColumn('ai_autopilot_actions', 'tenant_id')) {
            return;
        }

        DB::statement('ALTER TABLE ai_autopilot_actions DROP CONSTRAINT IF EXISTS ai_autopilot_actions_tenant_id_foreign');
        DB::statement('DROP INDEX IF EXISTS ai_autopilot_actions_tenant_id_run_id_index');

        Schema::table('ai_autopilot_actions', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });
    }
};
