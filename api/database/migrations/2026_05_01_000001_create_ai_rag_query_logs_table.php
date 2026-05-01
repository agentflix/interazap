<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create AI RAG query logs table for quality monitoring and analytics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_rag_query_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('query_hash', 64);
            $table->integer('query_length');
            $table->string('mode', 16);
            $table->integer('results_count');
            $table->decimal('top_score', 5, 4)->nullable();
            $table->decimal('avg_score', 5, 4)->nullable();
            $table->integer('latency_ms');
            $table->boolean('has_results');
            $table->timestamp('created_at')->index();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'created_at'], 'idx_rag_logs_tenant_created');
            $table->index(['has_results', 'created_at'], 'idx_rag_logs_results_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_rag_query_logs');
    }
};
