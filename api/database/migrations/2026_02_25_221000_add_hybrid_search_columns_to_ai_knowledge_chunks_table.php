<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE ai_knowledge_chunks
            ADD COLUMN content_tsv tsvector GENERATED ALWAYS AS (to_tsvector('portuguese', coalesce(content, ''))) STORED"
        );

        DB::statement('CREATE INDEX idx_ai_knowledge_chunks_content_tsv ON ai_knowledge_chunks USING GIN (content_tsv)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_ai_knowledge_chunks_content_tsv');
        DB::statement('ALTER TABLE ai_knowledge_chunks DROP COLUMN IF EXISTS content_tsv');
    }
};
