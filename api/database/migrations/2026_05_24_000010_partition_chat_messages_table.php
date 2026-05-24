<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isPartitioned = DB::selectOne(
            "SELECT 1 FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE c.relname = 'chat_messages' AND c.relkind = 'p' AND n.nspname = current_schema()"
        );

        if ($isPartitioned !== null) {
            return;
        }

        DB::transaction(function (): void {
            // Drop child FKs that point TO chat_messages (required before DROP TABLE)
            DB::statement('ALTER TABLE chat_messages_extended DROP CONSTRAINT IF EXISTS fk_chat_messages_extended_message_id');
            DB::statement('ALTER TABLE chat_message_interactions DROP CONSTRAINT IF EXISTS fk_chat_message_interactions_message_id');

            // Drop the monolithic table (bank is empty — no data loss)
            Schema::dropIfExists('chat_messages');

            // Create partitioned table — PK must include partition key (created_at)
            DB::statement('
                CREATE TABLE chat_messages (
                    id                UUID          NOT NULL,
                    tenant_id         UUID,
                    ticket_id         UUID,
                    user_id           UUID,
                    contact_id        UUID,
                    content           TEXT,
                    type              VARCHAR(30),
                    direction         VARCHAR(10),
                    is_from_contact   BOOLEAN,
                    source            VARCHAR(30),
                    status            VARCHAR(20),
                    external_id       VARCHAR(255),
                    metadata          JSONB,
                    sent_at           TIMESTAMP(0),
                    delivered_at      TIMESTAMP(0),
                    read_at           TIMESTAMP(0),
                    is_deleted        BOOLEAN       NOT NULL DEFAULT FALSE,
                    deleted_at        TIMESTAMP(0),
                    deleted_by        UUID,
                    transcription     TEXT,
                    audio_duration_ms INTEGER,
                    audio_mime_type   VARCHAR(50),
                    created_at        TIMESTAMP(0) NOT NULL DEFAULT NOW(),
                    updated_at        TIMESTAMP(0),
                    PRIMARY KEY (id, created_at)
                ) PARTITION BY RANGE (created_at)
            ');

            // Recreate indexes (become local per-partition automatically)
            DB::statement('CREATE INDEX idx_chat_messages_tenant_id    ON chat_messages (tenant_id)');
            DB::statement('CREATE INDEX idx_chat_messages_ticket_id    ON chat_messages (ticket_id)');
            DB::statement('CREATE INDEX idx_chat_messages_user_id      ON chat_messages (user_id)');
            DB::statement('CREATE INDEX idx_chat_messages_contact_id   ON chat_messages (contact_id)');
            DB::statement('CREATE INDEX idx_chat_messages_type         ON chat_messages (type)');
            DB::statement('CREATE INDEX idx_chat_messages_direction    ON chat_messages (direction)');
            DB::statement('CREATE INDEX idx_chat_messages_status       ON chat_messages (status)');
            DB::statement('CREATE INDEX idx_chat_messages_external_id  ON chat_messages (external_id)');
            DB::statement('CREATE INDEX idx_chat_messages_sent_at      ON chat_messages (sent_at)');
            DB::statement('CREATE INDEX idx_chat_messages_is_deleted   ON chat_messages (is_deleted)');
            // Critical composite index for keyset pagination in ListChatMessagesAction
            DB::statement('CREATE INDEX idx_chat_messages_stable_order ON chat_messages (tenant_id, ticket_id, created_at, id)');
            // BRIN index on created_at — cheap, optimal for range scans on insert-only data
            DB::statement('CREATE INDEX idx_chat_messages_created_brin ON chat_messages USING BRIN (created_at)');

            // Default partition — catches inserts outside explicit month ranges
            DB::statement('CREATE TABLE chat_messages_default PARTITION OF chat_messages DEFAULT');

            // Create monthly partitions: current month + next 3
            $this->createMonthlyPartitions(now()->startOfMonth(), 4);

            // Restore outgoing FKs on partitioned table (supported since PG 12)
            DB::statement('ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_tenant_id
                FOREIGN KEY (tenant_id) REFERENCES platform_tenants(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_ticket_id
                FOREIGN KEY (ticket_id) REFERENCES chat_tickets(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_user_id
                FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE SET NULL');

            // Child FKs (extended, interactions → chat_messages.id) are intentionally NOT restored.
            // PostgreSQL requires FK references to include the partition key (created_at), so
            // UNIQUE(id) alone is not possible on a RANGE(created_at) partitioned table.
            // Referential integrity for cascade deletes is enforced by the ChatMessage Eloquent model
            // via deleting related records in its deleting event or by application-level cleanup.

            // Setup pg_partman if available for auto-creation of future partitions
            $hasPartman = DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'pg_partman'");
            if ($hasPartman !== null) {
                DB::statement("
                    SELECT partman.create_parent(
                        p_parent_table => 'public.chat_messages',
                        p_control => 'created_at',
                        p_type => 'native',
                        p_interval => '1 month',
                        p_premake => 3
                    )
                ");
            }
        });
    }

    public function down(): void
    {
        $isPartitioned = DB::selectOne(
            "SELECT 1 FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE c.relname = 'chat_messages' AND c.relkind = 'p' AND n.nspname = current_schema()"
        );

        if ($isPartitioned === null) {
            return;
        }

        DB::transaction(function (): void {
            DB::statement('ALTER TABLE chat_messages_extended DROP CONSTRAINT IF EXISTS fk_chat_messages_extended_message_id');
            DB::statement('ALTER TABLE chat_message_interactions DROP CONSTRAINT IF EXISTS fk_chat_message_interactions_message_id');

            // DROP TABLE with CASCADE drops all child partitions
            DB::statement('DROP TABLE chat_messages CASCADE');

            // Restore monolithic schema (from 2026_01_01_000030_create_chat_core_tables.php:165-214)
            DB::statement('
                CREATE TABLE chat_messages (
                    id                UUID          NOT NULL,
                    tenant_id         UUID,
                    ticket_id         UUID,
                    user_id           UUID,
                    contact_id        UUID,
                    content           TEXT,
                    type              VARCHAR(30),
                    direction         VARCHAR(10),
                    is_from_contact   BOOLEAN,
                    source            VARCHAR(30),
                    status            VARCHAR(20),
                    external_id       VARCHAR(255),
                    metadata          JSONB,
                    sent_at           TIMESTAMP(0),
                    delivered_at      TIMESTAMP(0),
                    read_at           TIMESTAMP(0),
                    is_deleted        BOOLEAN       NOT NULL DEFAULT FALSE,
                    deleted_at        TIMESTAMP(0),
                    deleted_by        UUID,
                    transcription     TEXT,
                    audio_duration_ms INTEGER,
                    audio_mime_type   VARCHAR(50),
                    created_at        TIMESTAMP(0),
                    updated_at        TIMESTAMP(0),
                    PRIMARY KEY (id)
                )
            ');

            DB::statement('CREATE INDEX idx_chat_messages_tenant_id    ON chat_messages (tenant_id)');
            DB::statement('CREATE INDEX idx_chat_messages_ticket_id    ON chat_messages (ticket_id)');
            DB::statement('CREATE INDEX idx_chat_messages_user_id      ON chat_messages (user_id)');
            DB::statement('CREATE INDEX idx_chat_messages_contact_id   ON chat_messages (contact_id)');
            DB::statement('CREATE INDEX idx_chat_messages_type         ON chat_messages (type)');
            DB::statement('CREATE INDEX idx_chat_messages_direction    ON chat_messages (direction)');
            DB::statement('CREATE INDEX idx_chat_messages_status       ON chat_messages (status)');
            DB::statement('CREATE INDEX idx_chat_messages_external_id  ON chat_messages (external_id)');
            DB::statement('CREATE INDEX idx_chat_messages_sent_at      ON chat_messages (sent_at)');
            DB::statement('CREATE INDEX idx_chat_messages_is_deleted   ON chat_messages (is_deleted)');
            DB::statement('CREATE INDEX idx_chat_messages_stable_order ON chat_messages (tenant_id, ticket_id, created_at, id)');

            DB::statement('ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_tenant_id
                FOREIGN KEY (tenant_id) REFERENCES platform_tenants(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_ticket_id
                FOREIGN KEY (ticket_id) REFERENCES chat_tickets(id) ON DELETE CASCADE');
            DB::statement('ALTER TABLE chat_messages ADD CONSTRAINT fk_chat_messages_user_id
                FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE SET NULL');

            // Child FKs are not restored — see up() comment about PG partition PK constraint.
        });
    }

    private function createMonthlyPartitions(\Carbon\Carbon $startMonth, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $from = $startMonth->copy()->addMonths($i);
            $to = $from->copy()->addMonth();
            $name = 'chat_messages_'.$from->format('Y_m');

            DB::statement("
                CREATE TABLE IF NOT EXISTS {$name}
                PARTITION OF chat_messages
                FOR VALUES FROM ('{$from->toDateTimeString()}') TO ('{$to->toDateTimeString()}')
            ");
        }
    }
};
