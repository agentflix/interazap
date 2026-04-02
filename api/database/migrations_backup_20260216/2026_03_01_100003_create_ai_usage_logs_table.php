<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de logs de uso de IA.
 * Registra cada chamada à API com tokens e custos.
 * LGPD: dados devem ser purgados após 90 dias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->foreignUuid('ai_model_pricing_id')->nullable()->constrained('ai_model_pricings')->nullOnDelete();

            // Model identification
            $table->string('model_name', 100);
            $table->string('provider', 50)->nullable();

            // Token usage
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);

            // Cost tracking (USD)
            $table->decimal('input_cost', 12, 6)->default(0);
            $table->decimal('output_cost', 12, 6)->default(0);

            // Request context
            $table->string('request_id', 100)->nullable(); // External API request ID
            $table->string('feature', 100)->nullable(); // chat, automation, rag, etc.
            $table->unsignedInteger('latency_ms')->nullable();

            // Polymorphic relation to source (ticket, automation, etc.)
            $table->nullableUuidMorphs('usable');

            // Metadata
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Indexes for analytics and LGPD cleanup
            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['model_name', 'created_at']);
            $table->index('created_at'); // For LGPD purge job
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
