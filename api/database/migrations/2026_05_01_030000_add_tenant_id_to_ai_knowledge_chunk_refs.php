<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add tenant_id to ai_knowledge_chunk_refs to align with BelongsToTenant trait.
 *
 * The table was created without tenant_id, but the model uses BelongsToTenant
 * which applies a global TenantScope. This caused QueryException on every
 * Eloquent operation because the scope adds "where tenant_id = ?" automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_knowledge_chunk_refs', function (Blueprint $table): void {
            $table->uuid('tenant_id')
                ->after('document_id')
                ->nullable()
                ->index();
        });

        // Backfill tenant_id from the parent document so existing rows
        // (if any) remain valid under TenantScope.
        DB::statement('
            UPDATE ai_knowledge_chunk_refs
            SET tenant_id = ai_knowledge_documents.tenant_id
            FROM ai_knowledge_documents
            WHERE ai_knowledge_chunk_refs.document_id = ai_knowledge_documents.id
        ');

        Schema::table('ai_knowledge_chunk_refs', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable(false)->change();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_knowledge_chunk_refs', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex('ai_knowledge_chunk_refs_tenant_id_index');
            $table->dropColumn('tenant_id');
        });
    }
};
