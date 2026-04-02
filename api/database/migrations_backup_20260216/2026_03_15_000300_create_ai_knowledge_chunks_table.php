<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->uuid('tenant_id'); // Denormalized for search performance
            $table->integer('chunk_index');
            $table->text('content');
            $table->integer('token_count');
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('document_id')
                ->references('id')
                ->on('ai_knowledge_documents')
                ->onDelete('cascade');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->onDelete('cascade');

            // Indexes
            $table->index(['document_id', 'chunk_index'], 'idx_chunks_doc_order');
        });

        // Add vector column for embeddings (1536 dimensions for text-embedding-3-small)
        DB::statement('ALTER TABLE ai_knowledge_chunks ADD COLUMN embedding vector(1536)');

        // Create HNSW index for fast cosine similarity search
        // Using ivfflat for better performance with tenant_id filtering
        DB::statement('CREATE INDEX idx_chunks_embedding ON ai_knowledge_chunks USING hnsw (embedding vector_cosine_ops)');

        // Create composite index for tenant-scoped searches
        DB::statement('CREATE INDEX idx_chunks_tenant ON ai_knowledge_chunks (tenant_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_chunks');
    }
};
