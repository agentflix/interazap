<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de precificação de modelos de IA.
 * Armazena custos por modelo para cálculo de uso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_pricings', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Model identification
            $table->string('provider', 50); // openai, anthropic, google, etc.
            $table->string('model_name', 100); // gpt-4o, claude-3-opus, gemini-pro
            $table->string('display_name', 150)->nullable();

            // Pricing per million tokens (USD)
            $table->decimal('input_cost_per_1m', 10, 4)->default(0);
            $table->decimal('output_cost_per_1m', 10, 4)->default(0);

            // Context limits
            $table->unsignedInteger('max_context_tokens')->nullable();
            $table->unsignedInteger('max_output_tokens')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('pricing_effective_date')->nullable();

            // Metadata
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['provider', 'model_name']);
            $table->index(['is_active', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_pricings');
    }
};
