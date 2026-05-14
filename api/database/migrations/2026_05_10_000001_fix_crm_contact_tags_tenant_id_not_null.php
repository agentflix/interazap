<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration compensatória: popula tenant_id em crm_contact_tags via JOIN com
 * crm_contacts, remove órfãos irrecuperáveis e aplica NOT NULL.
 *
 * TASK-DB-001 — crm_contact_tags.tenant_id NOT NULL
 */
return new class extends Migration
{
    /**
     * Backfill → delete órfãos → ALTER COLUMN SET NOT NULL.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('crm_contact_tags', 'tenant_id')) {
            return;
        }

        // 1. Backfill: propagar tenant_id a partir do contato vinculado
        DB::statement(<<<'SQL'
            UPDATE crm_contact_tags cct
            SET    tenant_id = cc.tenant_id
            FROM   crm_contacts cc
            WHERE  cct.crm_contact_id = cc.id
              AND  cct.tenant_id IS NULL
        SQL);

        // 2. Limpar órfãos irrecuperáveis (contato deletado ou sem tenant_id)
        DB::statement(<<<'SQL'
            DELETE FROM crm_contact_tags
            WHERE  tenant_id IS NULL
        SQL);

        // 3. Aplicar NOT NULL constraint
        DB::statement(<<<'SQL'
            ALTER TABLE crm_contact_tags
            ALTER COLUMN tenant_id SET NOT NULL
        SQL);
    }

    /**
     * Reverte NOT NULL para nullable.
     */
    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE crm_contact_tags
            ALTER COLUMN tenant_id DROP NOT NULL
        SQL);
    }
};
