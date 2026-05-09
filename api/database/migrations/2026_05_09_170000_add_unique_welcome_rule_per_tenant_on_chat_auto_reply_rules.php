<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement(<<<'SQL'
                WITH ranked_welcome_rules AS (
                    SELECT
                        id,
                        ROW_NUMBER() OVER (
                            PARTITION BY tenant_id
                            ORDER BY created_at DESC, id DESC
                        ) AS row_num
                    FROM chat_auto_reply_rules
                    WHERE is_welcome = true
                )
                UPDATE chat_auto_reply_rules AS rules
                SET is_welcome = false
                FROM ranked_welcome_rules AS ranked
                WHERE rules.id = ranked.id
                    AND ranked.row_num > 1
            SQL);

            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX IF NOT EXISTS uq_chat_auto_reply_rules_welcome_per_tenant
                ON chat_auto_reply_rules (tenant_id)
                WHERE is_welcome = true
            SQL);
        });
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS uq_chat_auto_reply_rules_welcome_per_tenant'
        );
    }
};

