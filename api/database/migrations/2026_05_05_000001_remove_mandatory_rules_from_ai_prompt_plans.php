<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove mandatory_rules column from ai_prompt_plans table.
 *
 * This column was never used in the prompt resolution logic.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_prompt_plans', function (Blueprint $table): void {
            $table->dropColumn('mandatory_rules');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_prompt_plans', function (Blueprint $table): void {
            $table->jsonb('mandatory_rules')->nullable()->comment('Regras obrigatórias do plano');
        });
    }
};
