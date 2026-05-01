<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona coluna settings_chat (JSONB) à tabela platform_tenants.
 *
 * Armazena configurações globais de chat do tenant, incluindo:
 *   - auto_close_enabled: habilita/desabilita auto-fechamento no tenant
 *   - auto_close_after_minutes: minutos de inatividade para fechar
 *   - auto_close_target: alvo da inatividade ('both', 'client', 'agent')
 *   - auto_close_message: mensagem enviada ao cliente no fechamento
 *
 * Instâncias (chat_instances) podem sobrescrever estas configurações
 * via colunas auto_close_* próprias (null = herda do tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->jsonb('settings_chat')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->dropColumn('settings_chat');
        });
    }
};
