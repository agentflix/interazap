<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona configurações de transcrição de mídia à tabela platform_tenants.
 *
 * Cada tenant pode habilitar/desabilitar transcrição por tipo de mídia
 * e definir limites individuais de duração/quantidade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->boolean('media_transcription_audio_enabled')->default(false);
            $table->boolean('media_transcription_image_enabled')->default(false);
            $table->boolean('media_transcription_video_enabled')->default(false);
            $table->integer('media_transcription_audio_max_minutes')->default(5);
            $table->integer('media_transcription_image_max_per_message')->default(3);
            $table->integer('media_transcription_video_max_seconds')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'media_transcription_audio_enabled',
                'media_transcription_image_enabled',
                'media_transcription_video_enabled',
                'media_transcription_audio_max_minutes',
                'media_transcription_image_max_per_message',
                'media_transcription_video_max_seconds',
            ]);
        });
    }
};
