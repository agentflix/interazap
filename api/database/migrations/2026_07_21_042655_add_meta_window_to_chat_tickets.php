<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FEAT-006 — Meta WhatsApp: janela de atendimento 24h/72h CTWA.
 *
 * Adiciona a `chat_tickets` os campos necessários para persistir a janela
 * de atendimento (customer service window) da Cloud API da Meta e os
 * metadados de referral do Click-to-WhatsApp Ads (CTWA).
 *
 * `chat_tickets` não é particionada (apenas `chat_messages` foi
 * particionada por `created_at` em 2026_05_24_000010) — um
 * `ALTER TABLE ... ADD COLUMN` simples é seguro aqui.
 */
return new class extends Migration
{
    /**
     * Nome da constraint CHECK que restringe `meta_window_type` a '24h'/'72h'.
     */
    private const string TYPE_CHECK_CONSTRAINT = 'chat_tickets_meta_window_type_check';

    /**
     * Nome do índice sobre `meta_window_expires_at`.
     */
    private const string EXPIRES_AT_INDEX = 'idx_chat_tickets_meta_window_expires_at';

    public function up(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('chat_tickets', 'meta_window_expires_at')) {
                $table->timestamp('meta_window_expires_at')->nullable()
                    ->comment('Expiração da janela de atendimento Meta (24h padrão ou 72h CTWA), renovada a cada inbound.');
            }

            if (! Schema::hasColumn('chat_tickets', 'meta_window_type')) {
                $table->string('meta_window_type', 4)->nullable()
                    ->comment("Tipo da janela Meta corrente: '24h' (padrão) ou '72h' (CTWA/referral).");
            }

            if (! Schema::hasColumn('chat_tickets', 'meta_referral_source_id')) {
                $table->string('meta_referral_source_id')->nullable()
                    ->comment('ID do anúncio/post de origem do CTWA (messages[].referral.source_id).');
            }

            if (! Schema::hasColumn('chat_tickets', 'meta_referral_source_type')) {
                $table->string('meta_referral_source_type')->nullable()
                    ->comment('Tipo da origem do CTWA, ex: ad, post (messages[].referral.source_type).');
            }

            if (! Schema::hasColumn('chat_tickets', 'meta_referral_headline')) {
                $table->string('meta_referral_headline')->nullable()
                    ->comment('Título/headline do anúncio de origem do CTWA (messages[].referral.headline).');
            }

            if (! Schema::hasColumn('chat_tickets', 'meta_referral_ctwa_clid')) {
                $table->string('meta_referral_ctwa_clid')->nullable()
                    ->comment('Click ID do Click-to-WhatsApp Ads (messages[].referral.ctwa_clid).');
            }
        });

        $constraintExists = DB::selectOne('
            SELECT 1 FROM pg_constraint WHERE conname = ?
        ', [self::TYPE_CHECK_CONSTRAINT]);

        if (! $constraintExists) {
            DB::statement('
                ALTER TABLE chat_tickets
                ADD CONSTRAINT '.self::TYPE_CHECK_CONSTRAINT.'
                CHECK (meta_window_type IS NULL OR meta_window_type IN (\'24h\', \'72h\'))
            ');
        }

        $indexExists = DB::selectOne('
            SELECT 1 FROM pg_indexes WHERE indexname = ?
        ', [self::EXPIRES_AT_INDEX]);

        if (! $indexExists) {
            DB::statement('CREATE INDEX '.self::EXPIRES_AT_INDEX.' ON chat_tickets (meta_window_expires_at)');
        }
    }

    public function down(): void
    {
        $indexExists = DB::selectOne('
            SELECT 1 FROM pg_indexes WHERE indexname = ?
        ', [self::EXPIRES_AT_INDEX]);

        if ($indexExists) {
            DB::statement('DROP INDEX '.self::EXPIRES_AT_INDEX);
        }

        $constraintExists = DB::selectOne('
            SELECT 1 FROM pg_constraint WHERE conname = ?
        ', [self::TYPE_CHECK_CONSTRAINT]);

        if ($constraintExists) {
            DB::statement('ALTER TABLE chat_tickets DROP CONSTRAINT '.self::TYPE_CHECK_CONSTRAINT);
        }

        Schema::table('chat_tickets', function (Blueprint $table): void {
            foreach ([
                'meta_referral_ctwa_clid',
                'meta_referral_headline',
                'meta_referral_source_type',
                'meta_referral_source_id',
                'meta_window_type',
                'meta_window_expires_at',
            ] as $column) {
                if (Schema::hasColumn('chat_tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
