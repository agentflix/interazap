<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable pgvector extension for semantic search
        // Handles case where extension already exists
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We don't drop the extension in down() as it may be used by other tables
        // If really needed, uncomment the line below:
        // DB::statement('DROP EXTENSION IF EXISTS vector');
    }
};
