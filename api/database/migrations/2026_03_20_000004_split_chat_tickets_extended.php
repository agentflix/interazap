<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-004 Fase 3a — Split chat_tickets into core + extended
 *
 * Moves low-frequency columns to chat_tickets_extended:
 * subject, profile_picture_url, human_takeover_at, closed_by, close_reason,
 * auto_close_*, sla_* fields.
 *
 * Core table retains high-frequency columns for fast listing queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_tickets_extended') || ! Schema::hasColumn('chat_tickets', 'subject')) {
            return;
        }

        Schema::create('chat_tickets_extended', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id')->unique();
            $table->text('subject')->nullable();
            $table->string('profile_picture_url', 500)->nullable();
            $table->timestamp('human_takeover_at')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->string('close_reason', 100)->nullable();
            $table->unsignedInteger('auto_close_queue_after_minutes')->default(0);
            $table->unsignedInteger('auto_close_in_progress_after_minutes')->default(0);
            $table->timestamp('sla_first_response_due_at')->nullable();
            $table->timestamp('sla_resolution_due_at')->nullable();
            $table->boolean('sla_first_response_breached')->default(false);
            $table->boolean('sla_resolution_breached')->default(false);
            $table->timestamps();

            $table->foreign('ticket_id')
                ->references('id')
                ->on('chat_tickets')
                ->cascadeOnDelete();

            $table->foreign('closed_by')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            $table->index('ticket_id', 'idx_chat_tickets_extended_ticket');
        });

        // Migrate existing data
        DB::statement('
            INSERT INTO chat_tickets_extended (
                id, ticket_id, subject, profile_picture_url, human_takeover_at,
                closed_by, close_reason, auto_close_queue_after_minutes,
                auto_close_in_progress_after_minutes,
                sla_first_response_due_at, sla_resolution_due_at,
                sla_first_response_breached, sla_resolution_breached,
                created_at, updated_at
            )
            SELECT
                gen_random_uuid(), id, subject, profile_picture_url, human_takeover_at,
                closed_by, close_reason, auto_close_queue_after_minutes,
                auto_close_in_progress_after_minutes,
                sla_first_response_due_at, sla_resolution_due_at,
                sla_first_response_breached, sla_resolution_breached,
                created_at, updated_at
            FROM chat_tickets
        ');

        // Drop FK constraint for closed_by before dropping column
        $this->dropForeignKeyIfExists('chat_tickets', 'closed_by');

        Schema::table('chat_tickets', function (Blueprint $table): void {
            $table->dropColumn([
                'subject',
                'profile_picture_url',
                'human_takeover_at',
                'closed_by',
                'close_reason',
                'auto_close_queue_after_minutes',
                'auto_close_in_progress_after_minutes',
                'sla_first_response_due_at',
                'sla_resolution_due_at',
                'sla_first_response_breached',
                'sla_resolution_breached',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table): void {
            $table->text('subject')->nullable()->after('category');
            $table->string('profile_picture_url', 500)->nullable()->after('push_name');
            $table->timestamp('human_takeover_at')->nullable()->after('is_bot_active');
            $table->uuid('closed_by')->nullable()->after('closed_at');
            $table->string('close_reason', 100)->nullable()->after('closed_by');
            $table->unsignedInteger('auto_close_queue_after_minutes')->default(0)->after('close_reason');
            $table->unsignedInteger('auto_close_in_progress_after_minutes')->default(0)->after('auto_close_queue_after_minutes');
            $table->timestamp('sla_first_response_due_at')->nullable()->after('auto_close_in_progress_after_minutes');
            $table->timestamp('sla_resolution_due_at')->nullable()->after('sla_first_response_due_at');
            $table->boolean('sla_first_response_breached')->default(false)->after('sla_resolution_due_at');
            $table->boolean('sla_resolution_breached')->default(false)->after('sla_first_response_breached');

            $table->foreign('closed_by')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();
        });

        // Restore data from extended table
        DB::statement('
            UPDATE chat_tickets t
            SET subject = e.subject,
                profile_picture_url = e.profile_picture_url,
                human_takeover_at = e.human_takeover_at,
                closed_by = e.closed_by,
                close_reason = e.close_reason,
                auto_close_queue_after_minutes = e.auto_close_queue_after_minutes,
                auto_close_in_progress_after_minutes = e.auto_close_in_progress_after_minutes,
                sla_first_response_due_at = e.sla_first_response_due_at,
                sla_resolution_due_at = e.sla_resolution_due_at,
                sla_first_response_breached = e.sla_first_response_breached,
                sla_resolution_breached = e.sla_resolution_breached
            FROM chat_tickets_extended e
            WHERE e.ticket_id = t.id
        ');

        Schema::dropIfExists('chat_tickets_extended');
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $foreignKeys = DB::select("
            SELECT tc.constraint_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
            WHERE tc.table_name = ?
                AND kcu.column_name = ?
                AND tc.constraint_type = 'FOREIGN KEY'
        ", [$table, $column]);

        foreach ($foreignKeys as $fk) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$fk->constraint_name}");
        }
    }
};
