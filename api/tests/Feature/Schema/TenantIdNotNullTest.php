<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * TASK-DB-003 — Gate preventivo: valida que tenant_id é NOT NULL
 * nas tabelas críticas de multi-tenancy.
 *
 * Qualquer migration futura que introduza tenant_id nullable em tabela
 * listada faz este suite falhar antes de chegar à Validation.
 */
$tenantTables = [
    'crm_contact_tags',
    'ai_autopilot_approvals',
    // Adicionar novas tabelas auditáveis aqui
];

test('tenant_id é NOT NULL nas tabelas críticas de multi-tenancy', function (string $table): void {
    $row = DB::selectOne("
        SELECT is_nullable
        FROM   information_schema.columns
        WHERE  table_schema = 'public'
          AND  table_name   = ?
          AND  column_name  = 'tenant_id'
    ", [$table]);

    expect($row)->not->toBeNull("Tabela '{$table}' não possui coluna tenant_id");
    expect($row->is_nullable)->toBe('NO', "tenant_id em '{$table}' deveria ser NOT NULL");
})->with($tenantTables);
