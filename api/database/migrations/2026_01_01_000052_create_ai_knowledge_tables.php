<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Cria as tabelas de knowledge base e RAG do contexto AI (PostgreSQL + pgvector).
 *
 * Tabelas criadas:
 * - ai_knowledge_documents: Documentos de conhecimento
 * - ai_knowledge_chunks: Chunks vetoriais dos documentos
 * - ai_knowledge_chunk_refs: Referências entre chunks e documentos
 * - ai_rag_query_logs: Logs de consultas RAG
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_knowledge_documents')) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $this->createKnowledgeDocuments();
        $this->createKnowledgeChunks();
        $this->createKnowledgeChunkRefs();
        $this->createRagQueryLogs();
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_rag_query_logs');
        Schema::dropIfExists('ai_knowledge_chunk_refs');
        Schema::dropIfExists('ai_knowledge_chunks');
        Schema::dropIfExists('ai_knowledge_documents');
    }

    private function createKnowledgeDocuments(): void
    {
        Schema::create('ai_knowledge_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do documento (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono do documento');
            $table->string('name', 255)->comment('Nome do documento');
            $table->string('original_filename', 255)->comment('Nome original do arquivo');
            $table->string('file_path', 500)->comment('Caminho do arquivo no storage');
            $table->bigInteger('file_size_bytes')->comment('Tamanho do arquivo em bytes');
            $table->string('file_type', 20)->comment('Tipo MIME do arquivo');
            $table->integer('version')->default(1)->comment('Versão do documento');
            $table->uuid('replaced_by')->nullable()->comment('Documento que substituiu este');
            $table->integer('chunk_count')->default(0)->comment('Quantidade de chunks gerados');
            $table->string('embedding_status', 20)->default('pending')->comment('Status do embedding (pending/processing/completed/error)');
            $table->text('error_message')->nullable()->comment('Erro no processamento do documento');
            $table->jsonb('metadata')->nullable()->comment('Metadados (autor, data, etc.)');
            $table->boolean('is_active')->default(true)->comment('Se o documento está ativo');
            $table->timestamps(0);

            $table->foreign('tenant_id', 'fk_ai_knowledge_documents_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->index(['tenant_id', 'is_active'], 'idx_ai_knowledge_documents_tenant_active');
            $table->index(['embedding_status'], 'idx_ai_knowledge_documents_embedding_status');
            $table->index(['replaced_by'], 'idx_ai_knowledge_documents_replaced_by');
        });
    }

    private function createKnowledgeChunks(): void
    {
        Schema::create('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do chunk (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono do chunk');
            $table->uuid('document_id')->comment('Documento de origem');
            $table->integer('chunk_index')->comment('Índice sequencial do chunk no documento');
            $table->text('content')->comment('Conteúdo textual do chunk');
            $table->integer('token_count')->comment('Quantidade de tokens no chunk');
            $table->char('content_hash', 64)->comment('Hash SHA-256 do conteúdo para deduplicação');
            $table->timestamp('created_at', 0)->comment('Data de criação');

            $table->foreign('tenant_id', 'fk_ai_knowledge_chunks_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('document_id', 'fk_ai_knowledge_chunks_document_id')
                ->references('id')
                ->on('ai_knowledge_documents');
            $table->index(['tenant_id', 'document_id'], 'idx_ai_knowledge_chunks_tenant_document');
            $table->index(['document_id', 'chunk_index'], 'idx_ai_knowledge_chunks_document_index');
            $table->index(['content_hash'], 'idx_ai_knowledge_chunks_content_hash');
        });

        // Adiciona coluna vector(512) para embeddings (pgvector)
        DB::statement('ALTER TABLE ai_knowledge_chunks ADD COLUMN IF NOT EXISTS embedding vector(512) NULL');
        DB::statement("COMMENT ON COLUMN ai_knowledge_chunks.embedding IS 'Embedding vetorial (pgvector) para busca semântica'");

        // Adiciona coluna tsvector para full-text search
        DB::statement('ALTER TABLE ai_knowledge_chunks ADD COLUMN IF NOT EXISTS content_tsv tsvector NULL');
        DB::statement("COMMENT ON COLUMN ai_knowledge_chunks.content_tsv IS 'Índice full-text search (tsvector)'");

        // Cria índice GIN para tsvector
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ai_knowledge_chunks_content_tsv ON ai_knowledge_chunks USING GIN (content_tsv)');

        // Cria índice IVFFlat ou HNSW para vector (escolhemos HNSW para melhor recall)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ai_knowledge_chunks_embedding ON ai_knowledge_chunks USING hnsw (embedding vector_cosine_ops)');
    }

    private function createKnowledgeChunkRefs(): void
    {
        Schema::create('ai_knowledge_chunk_refs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único da referência (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono da referência');
            $table->uuid('document_id')->comment('Documento relacionado');
            $table->uuid('chunk_id')->comment('Chunk referenciado');
            $table->integer('chunk_index')->comment('Índice do chunk');
            $table->timestamp('created_at', 0)->comment('Data de criação');
            $table->timestamp('updated_at', 0)->nullable()->comment('Data de atualização');

            $table->foreign('tenant_id', 'fk_ai_knowledge_chunk_refs_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('chunk_id', 'fk_ai_knowledge_chunk_refs_chunk_id')
                ->references('id')
                ->on('ai_knowledge_chunks');
            $table->foreign('document_id', 'fk_ai_knowledge_chunk_refs_document_id')
                ->references('id')
                ->on('ai_knowledge_documents');
            $table->index(['tenant_id', 'chunk_id'], 'idx_ai_knowledge_chunk_refs_tenant_chunk');
            $table->index(['chunk_id', 'document_id'], 'idx_ai_knowledge_chunk_refs_chunk_document');
            $table->index(['document_id'], 'idx_ai_knowledge_chunk_refs_document_id');
        });
    }

    private function createRagQueryLogs(): void
    {
        Schema::create('ai_rag_query_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do log (UUID)');
            $table->uuid('tenant_id')->comment('Tenant dono da consulta');
            $table->string('query_hash', 64)->comment('Hash da query');
            $table->integer('query_length')->comment('Comprimento da query');
            $table->string('mode', 16)->comment('Modo de consulta');
            $table->integer('results_count')->comment('Quantidade de resultados retornados');
            $table->decimal('top_score', 5, 4)->nullable()->comment('Score do melhor resultado (0-1)');
            $table->decimal('avg_score', 5, 4)->nullable()->comment('Score médio dos resultados (0-1)');
            $table->integer('latency_ms')->comment('Latência da busca em ms');
            $table->boolean('has_results')->comment('Se teve resultados');
            $table->timestamp('created_at', 0)->comment('Data de criação');
            $table->timestamp('updated_at', 0)->nullable()->comment('Data de atualização');

            $table->foreign('tenant_id', 'fk_ai_rag_query_logs_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->index(['tenant_id', 'created_at'], 'idx_ai_rag_query_logs_tenant_created');
            $table->index(['created_at'], 'idx_ai_rag_query_logs_created_at');
            $table->index(['query_hash'], 'idx_ai_rag_query_logs_query_hash');
            $table->index(['latency_ms'], 'idx_ai_rag_query_logs_latency');
        });
    }
};
