<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona colunas de configuração de auto-fechamento à tabela chat_instances.
 *
 * Cada instância pode sobrescrever as configurações globais do tenant.
 * Todas as colunas são nullable com default null — isso significa "herda do tenant".
 *
 * Hierarquia de configuração:
 *   1. chat_instances.auto_close_*  (se não-null, usa este valor)
 *   2. platform_tenants.settings_chat->auto_close_*  (fallback do tenant)
 *
 * Colunas:
 *   - auto_close_enabled: boolean, null = herda do tenant
 *   - auto_close_after_minutes: minutos de inatividade, null = herda
 *   - auto_close_target: 'both' | 'client' | 'agent', null = herda
 *   - auto_close_message: mensagem de fechamento, null = herda
 *
 * Colunas legacy em chat_tickets_extended são MANTIDAS (deprecadas apenas):
 *   - auto_close_queue_after_minutes
 *   - auto_close_in_progress_after_minutes
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_instances', function (Blueprint $table): void {
            $table->boolean('auto_close_enabled')->nullable();
            $table->unsignedSmallInteger('auto_close_after_minutes')->nullable();
            $table->string('auto_close_target', 10)->nullable();
            $table->text('auto_close_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_instances', function (Blueprint $table): void {
            $table->dropColumn([
                'auto_close_enabled',
                'auto_close_after_minutes',
                'auto_close_target',
                'auto_close_message',
            ]);
        });
    }
};
