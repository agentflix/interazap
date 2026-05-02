<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona colunas de atividade segmentada para auto-fechamento por inatividade.
 *
 * Separa o tracking de última mensagem por direção (cliente vs atendente),
 * permitindo políticas de inatividade distintas:
 *   - last_customer_message_at: última mensagem do cliente (direction = 'incoming')
 *   - last_agent_message_at: última mensagem do atendente (direction = 'outgoing')
 *
 * Índices compostos otimizam a query batch que varre tickets abertos
 * filtrando por tenant, status ativo e última atividade segmentada.
 *
 * NOTA: O índice de batch query (tenant_id, status, last_message_at DESC)
 * já existe como idx_chat_tickets_tenant_status_last_message na migration
 * 2026_01_01_000099_create_performance_indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table): void {
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamp('last_agent_message_at')->nullable();

            $table->index(
                ['tenant_id', 'status', 'last_customer_message_at'],
                'idx_tickets_activity_target'
            );

            $table->index(
                ['tenant_id', 'status', 'last_agent_message_at'],
                'idx_tickets_activity_agent'
            );
        });
    }

    public function down(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table): void {
            $table->dropIndex('idx_tickets_activity_target');
            $table->dropIndex('idx_tickets_activity_agent');

            $table->dropColumn([
                'last_customer_message_at',
                'last_agent_message_at',
            ]);
        });
    }
};
