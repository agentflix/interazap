<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona coluna source à tabela chat_messages.
 *
 * Esta coluna identifica a origem da mensagem (webhook, agent, system, bot)
 * para permitir ativação automática do Human Takeover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->string('source')->default('webhook')->after('is_from_contact');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
};
