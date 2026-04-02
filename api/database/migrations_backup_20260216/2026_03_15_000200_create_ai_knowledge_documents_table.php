<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_knowledge_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name', 255);
            $table->string('original_filename', 255);
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

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->onDelete('cascade');

            // Indexes for common queries
            $table->index(['tenant_id', 'is_active'], 'idx_docs_tenant_active');
            $table->index(['tenant_id', 'embedding_status'], 'idx_docs_tenant_status');
            $table->index(['tenant_id', 'name'], 'idx_docs_tenant_name');
        });

        // Add self-referencing foreign key after table creation
        try {
            Schema::table('ai_knowledge_documents', function (Blueprint $table): void {
                $table->foreign('replaced_by')
                    ->references('id')
                    ->on('ai_knowledge_documents')
                    ->onDelete('set null');
            });
        } catch (QueryException) {
            // Avoid failing the migration if the FK cannot be created.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_documents');
    }
};
