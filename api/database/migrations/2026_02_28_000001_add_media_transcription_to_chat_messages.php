<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona colunas de transcrição de mídia à tabela chat_messages.
 *
 * Suporta transcrição de áudio (Whisper), descrição de imagem (Vision)
 * e transcrição de vídeo (Whisper + Vision) para o Autopilot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->text('media_transcription')->nullable()->after('file_size');
            $table->string('media_transcription_provider', 50)->nullable()->after('media_transcription');
            $table->string('media_transcription_status', 20)->nullable()->after('media_transcription_provider');
            $table->integer('media_transcription_tokens')->nullable()->after('media_transcription_status');
            $table->decimal('media_transcription_cost', 10, 6)->nullable()->after('media_transcription_tokens');
            $table->timestamp('media_transcribed_at')->nullable()->after('media_transcription_cost');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn([
                'media_transcription',
                'media_transcription_provider',
                'media_transcription_status',
                'media_transcription_tokens',
                'media_transcription_cost',
                'media_transcribed_at',
            ]);
        });
    }
};
