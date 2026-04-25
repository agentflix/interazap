<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add partial index for phone_number_id lookup on chat_instances
 *
 * Query pattern: WHERE settings_json->>'phone_number_id' = 'xxx' AND provider = 'meta'
 * This partial index only covers rows where provider = 'meta' for efficiency.
 *
 * Reference: PLAN-039 section 6 — Database Migration
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX idx_phone_number_id_lookup ON chat_instances ((settings_json->>'phone_number_id')) WHERE provider = 'meta'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_phone_number_id_lookup');
    }
};
