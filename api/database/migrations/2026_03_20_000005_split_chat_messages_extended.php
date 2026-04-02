<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN-004 Fase 3b — Split chat_messages into core + extended
 *
 * Moves low-frequency columns to chat_messages_extended:
 * file_*, media_transcription_*, reactions, edit_*, error_message.
 *
 * Core table retains high-frequency columns for fast listing/pagination.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages_extended', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('message_id')->unique();
            $table->string('file_url', 500)->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('media_transcription')->nullable();
            $table->string('media_transcription_provider', 50)->nullable();
            $table->string('media_transcription_status', 20)->nullable();
            $table->unsignedInteger('media_transcription_tokens')->nullable();
            $table->decimal('media_transcription_cost', 10, 6)->nullable();
            $table->timestamp('media_transcribed_at')->nullable();
            $table->jsonb('reactions')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->jsonb('edit_history')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->foreign('message_id')
                ->references('id')
                ->on('chat_messages')
                ->cascadeOnDelete();

            $table->index('message_id', 'idx_chat_messages_extended_message');
        });

        // Migrate existing data
        DB::statement('
            INSERT INTO chat_messages_extended (
                id, message_id, file_url, file_name, mime_type, file_size,
                media_transcription, media_transcription_provider,
                media_transcription_status, media_transcription_tokens,
                media_transcription_cost, media_transcribed_at,
                reactions, is_edited, edited_at, edit_history, error_message,
                created_at, updated_at
            )
            SELECT
                gen_random_uuid(), id, file_url, file_name, mime_type, file_size,
                media_transcription, media_transcription_provider,
                media_transcription_status, media_transcription_tokens,
                media_transcription_cost, media_transcribed_at,
                reactions, is_edited, edited_at, edit_history, error_message,
                created_at, updated_at
            FROM chat_messages
        ');

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn([
                'file_url',
                'file_name',
                'mime_type',
                'file_size',
                'media_transcription',
                'media_transcription_provider',
                'media_transcription_status',
                'media_transcription_tokens',
                'media_transcription_cost',
                'media_transcribed_at',
                'reactions',
                'is_edited',
                'edited_at',
                'edit_history',
                'error_message',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->string('file_url', 500)->nullable()->after('status');
            $table->string('file_name')->nullable()->after('file_url');
            $table->string('mime_type', 100)->nullable()->after('file_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->text('media_transcription')->nullable()->after('file_size');
            $table->string('media_transcription_provider', 50)->nullable()->after('media_transcription');
            $table->string('media_transcription_status', 20)->nullable()->after('media_transcription_provider');
            $table->unsignedInteger('media_transcription_tokens')->nullable()->after('media_transcription_status');
            $table->decimal('media_transcription_cost', 10, 6)->nullable()->after('media_transcription_tokens');
            $table->timestamp('media_transcribed_at')->nullable()->after('media_transcription_cost');
            $table->jsonb('reactions')->nullable()->after('metadata');
            $table->boolean('is_edited')->default(false)->after('reactions');
            $table->timestamp('edited_at')->nullable()->after('is_edited');
            $table->jsonb('edit_history')->nullable()->after('edited_at');
            $table->string('error_message')->nullable()->after('edit_history');
        });

        // Restore data from extended table
        DB::statement('
            UPDATE chat_messages m
            SET file_url = e.file_url,
                file_name = e.file_name,
                mime_type = e.mime_type,
                file_size = e.file_size,
                media_transcription = e.media_transcription,
                media_transcription_provider = e.media_transcription_provider,
                media_transcription_status = e.media_transcription_status,
                media_transcription_tokens = e.media_transcription_tokens,
                media_transcription_cost = e.media_transcription_cost,
                media_transcribed_at = e.media_transcribed_at,
                reactions = e.reactions,
                is_edited = e.is_edited,
                edited_at = e.edited_at,
                edit_history = e.edit_history,
                error_message = e.error_message
            FROM chat_messages_extended e
            WHERE e.message_id = m.id
        ');

        Schema::dropIfExists('chat_messages_extended');
    }
};
