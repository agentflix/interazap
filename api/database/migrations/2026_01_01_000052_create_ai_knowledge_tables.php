<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI Domain Knowledge Tables - Consolidated Migration
 *
 * Creates RAG (Retrieval-Augmented Generation) infrastructure:
 * - Enables pgvector extension for vector similarity search
 * - ai_knowledge_documents: Document metadata and status
 * - ai_knowledge_chunks: Document chunks with embeddings
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // ENABLE PGVECTOR EXTENSION
        // =====================================================================
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        // =====================================================================
        // AI_KNOWLEDGE_DOCUMENTS
        // =====================================================================
        Schema::create('ai_knowledge_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('original_filename');
            $table->string('file_path', 500);
            $table->bigInteger('file_size_bytes');
            $table->string('file_type', 20); // txt, csv, markdown, json
            $table->integer('version')->default(1);
            $table->uuid('replaced_by')->nullable();
            $table->integer('chunk_count')->default(0);
            $table->string('embedding_status', 20)->default('pending'); // pending, processing, ready, failed
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'is_active'], 'idx_docs_tenant_active');
            $table->index(['tenant_id', 'embedding_status'], 'idx_docs_tenant_status');
            $table->index(['tenant_id', 'name'], 'idx_docs_tenant_name');
        });

        // Self-reference FK must be added after table creation
        Schema::table('ai_knowledge_documents', function (Blueprint $table): void {
            $table->foreign('replaced_by')
                ->references('id')
                ->on('ai_knowledge_documents')
                ->nullOnDelete();
        });

        // =====================================================================
        // AI_KNOWLEDGE_CHUNKS - Vector embeddings for semantic search
        // =====================================================================
        Schema::create('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->uuid('tenant_id');
            $table->integer('chunk_index');
            $table->text('content');
            $table->integer('token_count');
            $table->timestamp('created_at');

            $table->foreign('document_id')
                ->references('id')
                ->on('ai_knowledge_documents')
                ->cascadeOnDelete();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['document_id', 'chunk_index'], 'idx_chunks_doc_order');
            $table->index('tenant_id', 'idx_chunks_tenant');
        });

        // Add vector column (1536 dimensions for OpenAI embeddings)
        DB::statement('ALTER TABLE ai_knowledge_chunks ADD COLUMN embedding vector(1536)');

        // Create HNSW index for fast similarity search
        DB::statement('
            CREATE INDEX idx_chunks_embedding
            ON ai_knowledge_chunks
            USING hnsw (embedding vector_cosine_ops)
            WITH (m = 16, ef_construction = 64)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_chunks');
        Schema::dropIfExists('ai_knowledge_documents');
    }
};
