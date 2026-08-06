<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Harden Meta webhook identity.
 *
 * 1. Cria `chat_message_identities` — tabela auxiliar que impõe identidade
 *    única de mensagem por tenant + instância + external ID. Necessária
 *    porque `chat_messages` é particionada por RANGE(created_at): a PK é
 *    (id, created_at) e uma UNIQUE no escopo desejado exigiria incluir a
 *    coluna de partição, o que não impede duplicata do mesmo WAMID.
 * 2. Cria índices únicos parciais em `chat_instances` para
 *    `phone_number_id`/`waba_id` de instâncias Meta ativas — rejeita
 *    configuração ambígua no nível do banco (fail-closed).
 *
 * Migration reversível e sem backfill destrutivo (nenhum UPDATE/DELETE).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_message_identities')) {
            Schema::create('chat_message_identities', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da identidade');
                $table->uuid('tenant_id')->comment('Tenant proprietário da mensagem');
                $table->uuid('instance_id')->comment('Instância de chat que recebeu/emitiu a mensagem');
                $table->string('external_id', 255)->comment('ID externo da mensagem (WAMID no caso Meta)');
                $table->uuid('message_id')->nullable()->comment('ID da mensagem em chat_messages, quando persistida');
                $table->timestamps(0);

                // Identidade única por tenant + instância + external ID —
                // reentrega concorrente do mesmo WAMID não duplica.
                $table->unique(
                    ['tenant_id', 'instance_id', 'external_id'],
                    'uq_chat_message_identities_tenant_instance_external',
                );

                $table->index(['tenant_id', 'created_at'], 'idx_chat_message_identities_tenant_created');
                $table->index('message_id', 'idx_chat_message_identities_message_id');

                $table->foreign('tenant_id', 'fk_chat_message_identities_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');

                $table->foreign('instance_id', 'fk_chat_message_identities_instance_id')
                    ->references('id')
                    ->on('chat_instances')
                    ->onDelete('cascade');
            });
        }

        // Unicidade segura de phone_number_id em instâncias Meta ATIVAS —
        // lookup ambíguo falha na escrita, nunca na leitura.
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS uq_chat_instances_meta_phone_number_active
            ON chat_instances ((settings_json->>'phone_number_id'))
            WHERE provider = 'meta' AND is_active = true AND settings_json->>'phone_number_id' IS NOT NULL
        ");

        // Unicidade segura de waba_id em instâncias Meta ATIVAS.
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS uq_chat_instances_meta_waba_active
            ON chat_instances ((settings_json->>'waba_id'))
            WHERE provider = 'meta' AND is_active = true AND settings_json->>'waba_id' IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_identities');

        DB::statement('DROP INDEX IF EXISTS uq_chat_instances_meta_phone_number_active');
        DB::statement('DROP INDEX IF EXISTS uq_chat_instances_meta_waba_active');
    }
};
