<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE chat_instances
            SET webhook_token = settings_json->>'token'
            WHERE provider = 'uazapi'
              AND settings_json->>'token' IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        // sem ação: tokens originais eram gerados aleatoriamente
    }
};
