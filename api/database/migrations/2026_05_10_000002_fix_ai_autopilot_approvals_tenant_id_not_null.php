<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration compensatória: popula tenant_id em ai_autopilot_approvals via JOIN
 * com ai_autopilot_runs, remove aprovações órfãs e aplica NOT NULL.
 *
 * TASK-DB-002 — ai_autopilot_approvals.tenant_id NOT NULL
 */
return new class extends Migration
{
    /**
     * Backfill → delete órfãos → ALTER COLUMN SET NOT NULL.
     * Guarda Schema::hasColumn para ambientes que nunca rodaram 2026_03_29.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('ai_autopilot_approvals', 'tenant_id')) {
            return;
        }

        // 1. Backfill: propagar tenant_id a partir da run vinculada
        DB::statement(<<<'SQL'
            UPDATE ai_autopilot_approvals aaa
            SET    tenant_id = aar.tenant_id
            FROM   ai_autopilot_runs aar
            WHERE  aaa.run_id = aar.id
              AND  aaa.tenant_id IS NULL
        SQL);

        // 2. Limpar aprovações irrecuperáveis (run deletada ou run sem tenant_id)
        DB::statement(<<<'SQL'
            DELETE FROM ai_autopilot_approvals
            WHERE  tenant_id IS NULL
        SQL);

        // 3. Aplicar NOT NULL constraint
        DB::statement(<<<'SQL'
            ALTER TABLE ai_autopilot_approvals
            ALTER COLUMN tenant_id SET NOT NULL
        SQL);
    }

    /**
     * Reverte NOT NULL para nullable.
     */
    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE ai_autopilot_approvals
            ALTER COLUMN tenant_id DROP NOT NULL
        SQL);
    }
};
