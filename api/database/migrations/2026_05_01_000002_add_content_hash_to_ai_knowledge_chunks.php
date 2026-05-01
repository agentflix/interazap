<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add content_hash to ai_knowledge_chunks and create chunk refs table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ensure pgcrypto is available for SHA-256 backfill
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        // =====================================================================
        // ADD content_hash TO ai_knowledge_chunks
        // =====================================================================
        Schema::table('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->char('content_hash', 64)
                ->default('0000000000000000000000000000000000000000000000000000000000000000')
                ->comment('SHA-256 hex digest of chunk content for deduplication');
        });

        // Backfill existing rows with SHA-256 of content
        DB::statement("UPDATE ai_knowledge_chunks SET content_hash = encode(digest(content::bytea, 'sha256'), 'hex')");

        // Remove temporary default so future inserts must provide the hash explicitly
        DB::statement('ALTER TABLE ai_knowledge_chunks ALTER COLUMN content_hash DROP DEFAULT');

        // Add composite index for tenant-scoped deduplication lookups
        Schema::table('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->index(['tenant_id', 'content_hash'], 'idx_chunks_tenant_hash');
        });

        // =====================================================================
        // CREATE ai_knowledge_chunk_refs
        // =====================================================================
        Schema::create('ai_knowledge_chunk_refs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->uuid('chunk_id');
            $table->integer('chunk_index');
            $table->timestamp('created_at');
            $table->timestamp('updated_at')->nullable();

            $table->foreign('document_id')
                ->references('id')
                ->on('ai_knowledge_documents')
                ->cascadeOnDelete();

            $table->foreign('chunk_id')
                ->references('id')
                ->on('ai_knowledge_chunks')
                ->cascadeOnDelete();

            $table->index(['document_id', 'chunk_index'], 'idx_chunk_refs_doc_order');
            $table->unique(['document_id', 'chunk_id'], 'uniq_chunk_refs_doc_chunk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_chunk_refs');

        Schema::table('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->dropIndex('idx_chunks_tenant_hash');
            $table->dropColumn('content_hash');
        });
    }
};
