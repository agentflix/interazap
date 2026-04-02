<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona coluna human_takeover_at à tabela chat_tickets.
 *
 * Esta coluna controla quando um atendente humano assumiu o atendimento,
 * bloqueando todas as automações (Bot/IA) até ser liberado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table): void {
            $table->timestamp('human_takeover_at')->nullable()->after('is_bot_active');
        });
    }

    public function down(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table): void {
            $table->dropColumn('human_takeover_at');
        });
    }
};
