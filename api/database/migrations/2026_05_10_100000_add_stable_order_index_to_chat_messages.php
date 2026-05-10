<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona índice composto para ordenação estável de mensagens.
 *
 * O índice cobre o padrão de consulta mais comum:
 *   WHERE tenant_id = ? AND ticket_id = ? ORDER BY created_at, id
 *
 * Isso elimina a necessidade de sort em memória e garante ordem
 * determinística mesmo quando múltiplas mensagens compartilham o
 * mesmo created_at (empate resolvido por id).
 *
 * Regra CANÔNICA de ordenação:
 *   ASC : ORDER BY created_at ASC, id ASC
 *   DESC: ORDER BY created_at DESC, id DESC
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE INDEX IF NOT EXISTS idx_chat_messages_stable_order'
            .' ON chat_messages(tenant_id, ticket_id, created_at, id)',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_chat_messages_stable_order');
    }
};
