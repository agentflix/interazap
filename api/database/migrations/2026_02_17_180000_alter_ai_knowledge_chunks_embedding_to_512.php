<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_chunks_embedding');
        DB::statement('ALTER TABLE ai_knowledge_chunks ALTER COLUMN embedding TYPE vector(512)');
        DB::statement('
            CREATE INDEX idx_chunks_embedding
            ON ai_knowledge_chunks
            USING hnsw (embedding vector_cosine_ops)
            WITH (m = 16, ef_construction = 64)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_chunks_embedding');
        DB::statement('ALTER TABLE ai_knowledge_chunks ALTER COLUMN embedding TYPE vector(1536)');
        DB::statement('
            CREATE INDEX idx_chunks_embedding
            ON ai_knowledge_chunks
            USING hnsw (embedding vector_cosine_ops)
            WITH (m = 16, ef_construction = 64)
        ');
    }
};
