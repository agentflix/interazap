<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX IF NOT EXISTS idx_chat_instances_settings_token ON chat_instances ((settings_json->>'token'))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_chat_instances_settings_token');
    }
};
