<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona índice composto para suportar cursor-based pagination eficiente na view Kanban.
 *
 * O índice cobre a query: WHERE tenant_id = ? AND crm_negotiation_funnel_id = ?
 *   AND crm_negotiation_funnel_step_id = ? AND status = 'open'
 *   AND (position > ? OR (position = ? AND id > ?)) AND deleted_at IS NULL
 * ORDER BY position ASC, id ASC
 * LIMIT N
 *
 * Como é um partial index (WHERE deleted_at IS NULL), o PostgreSQL faz Index Only Scan
 * sem acesso à tabela heap, reduzindo drasticamente o custo em tabelas grandes.
 */
return new class extends Migration
{
    /**
     * Criar índice composto para cursor pagination no Kanban.
     */
    public function up(): void
    {
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_crm_negotiations_kanban_cursor
            ON crm_negotiations (
                tenant_id,
                crm_negotiation_funnel_id,
                crm_negotiation_funnel_step_id,
                status,
                position ASC,
                id ASC
            )
            WHERE deleted_at IS NULL
        ');
    }

    /**
     * Remover índice de cursor pagination.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_crm_negotiations_kanban_cursor');
    }
};
