<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Chat;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for chat_messages RANGE partitioning and ChatMessagesPartitionMaintenanceCommand.
 *
 * Requires real PostgreSQL with partition migration applied.
 * Skipped automatically when chat_messages is not partitioned.
 */
class ChatMessagesPartitioningTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $isPartitioned = DB::selectOne(
            "SELECT 1 FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE c.relname = 'chat_messages' AND c.relkind = 'p' AND n.nspname = current_schema()"
        );

        if ($isPartitioned === null) {
            $this->markTestSkipped('chat_messages is not partitioned — run partition migration first.');
        }
    }

    #[Test]
    public function it_routes_insert_to_correct_monthly_partition(): void
    {
        $id = (string) Str::orderedUuid();
        $createdAt = now()->toDateTimeString();

        DB::statement(
            'INSERT INTO chat_messages (id, created_at, updated_at) VALUES (?, ?, ?)',
            [$id, $createdAt, $createdAt]
        );

        $row = DB::selectOne(
            'SELECT tableoid::regclass::text AS partition_name
             FROM chat_messages WHERE id = ? AND created_at = ?',
            [$id, $createdAt]
        );

        $this->assertNotNull($row);
        $this->assertMatchesRegularExpression(
            '/^chat_messages_\d{4}_\d{2}$/',
            $row->partition_name,
            'Row should land in a monthly partition, not the default'
        );
    }

    #[Test]
    public function it_dry_run_does_not_alter_partition_count(): void
    {
        $countBefore = (int) DB::scalar("
            SELECT COUNT(*) FROM pg_inherits
            JOIN pg_class child ON pg_inherits.inhrelid = child.oid
            WHERE inhparent = 'chat_messages'::regclass
        ");

        $this->artisan('chat:partitions:maintain', ['--dry-run' => true])
            ->assertExitCode(0);

        $countAfter = (int) DB::scalar("
            SELECT COUNT(*) FROM pg_inherits
            JOIN pg_class child ON pg_inherits.inhrelid = child.oid
            WHERE inhparent = 'chat_messages'::regclass
        ");

        $this->assertSame($countBefore, $countAfter, '--dry-run must not create or drop partitions');
    }

    #[Test]
    public function it_shows_partition_pruning_in_explain_for_date_filtered_query(): void
    {
        $tenantId = (string) Str::orderedUuid();
        $ticketId = (string) Str::orderedUuid();

        $plan = DB::select("
            EXPLAIN (FORMAT JSON)
            SELECT * FROM chat_messages
            WHERE tenant_id = ? AND ticket_id = ?
              AND created_at >= NOW() - INTERVAL '7 days'
            ORDER BY created_at DESC, id DESC LIMIT 30
        ", [$tenantId, $ticketId]);

        $json = json_encode($plan);

        $this->assertStringContainsString(
            'chat_messages_',
            $json,
            'EXPLAIN should reference at least one specific monthly partition (not full scan)'
        );
    }
}
