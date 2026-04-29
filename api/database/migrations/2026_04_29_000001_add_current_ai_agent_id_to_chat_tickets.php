<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona current_ai_agent_id a chat_tickets para sticky agent.
 *
 * Quando um agente IA delega para um especialista, este campo é preenchido
 * com o UUID do especialista. Mensagens seguintes vão direto ao especialista,
 * pulando o roteador (Sofia), economizando 1 LLM call por turno.
 *
 * Resetado em: transfer_to_human, releaseToAi, ticket fechado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table): void {
            $table->uuid('current_ai_agent_id')->nullable()->after('is_bot_active');
        });
    }

    public function down(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table): void {
            $table->dropColumn('current_ai_agent_id');
        });
    }
};
