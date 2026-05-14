<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FEAT-041 — Integração Telegram Bot API
 * TASK-T23 — Adicionar suporte ao canal 'telegram' na coluna chat_tickets.channel
 *
 * Schema context:
 * - chat_tickets.channel is VARCHAR(20) DEFAULT 'whatsapp'
 * - No CHECK constraint exists on this column — validation is enforced
 *   at the application layer (ChatTicketDTO, ChatWebhookIngestor)
 * - This migration verifies if a CHECK constraint exists and updates it
 *   to include 'telegram'. If none exists, it's a no-op (idempotent).
 *
 * Accepted channel values after this migration:
 *   whatsapp, telegram, email, webchat, instagram, messenger
 *
 * Backward compatible — no data is modified, no column type changes.
 */
return new class extends Migration
{
    /**
     * Known CHECK constraint name pattern for chat_tickets.channel.
     */
    private const CONSTRAINT_NAME = 'chat_tickets_channel_check';

    /**
     * Allowed channel values (superset including telegram).
     */
    private const ALLOWED_CHANNELS = [
        'whatsapp',
        'telegram',
        'email',
        'webchat',
        'instagram',
        'messenger',
    ];

    public function up(): void
    {
        // Check if a CHECK constraint exists on chat_tickets.channel
        $constraint = DB::selectOne("
            SELECT con.conname, pg_get_constraintdef(con.oid) AS definition
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
            WHERE rel.relname = 'chat_tickets'
              AND con.contype = 'c'
              AND pg_get_constraintdef(con.oid) LIKE '%channel%'
            LIMIT 1
        ");

        if ($constraint) {
            // Drop existing constraint and recreate with telegram included
            DB::statement("ALTER TABLE chat_tickets DROP CONSTRAINT \"{$constraint->conname}\"");

            $values = collect(self::ALLOWED_CHANNELS)
                ->map(fn (string $v): string => "'{$v}'")
                ->implode(', ');

            DB::statement('
                ALTER TABLE chat_tickets
                ADD CONSTRAINT '.self::CONSTRAINT_NAME."
                CHECK (channel::text = ANY (ARRAY[{$values}]::text[]))
            ");
        }

        // If no constraint exists, this is a no-op.
        // Channel validation is handled at the application layer:
        //   - api/src/Domain/Chat/DTOs/ChatTicketDTO.php
        //   - api/src/Domain/Chat/Actions/ChatWebhookIngestor.php

        DB::statement("COMMENT ON COLUMN chat_tickets.channel IS 'Communication channel: whatsapp, telegram, email, webchat, instagram, messenger. Validated at application layer.'");
    }

    public function down(): void
    {
        // Check if we added the constraint
        $constraint = DB::selectOne("
            SELECT con.conname, pg_get_constraintdef(con.oid) AS definition
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
            WHERE rel.relname = 'chat_tickets'
              AND con.conname = '".self::CONSTRAINT_NAME."'
            LIMIT 1
        ");

        if ($constraint) {
            // Remove the constraint we added (restoring original state — no constraint)
            DB::statement('ALTER TABLE chat_tickets DROP CONSTRAINT '.self::CONSTRAINT_NAME);
        }

        // Restore original column comment (no channel list)
        DB::statement('COMMENT ON COLUMN chat_tickets.channel IS NULL');
    }
};
