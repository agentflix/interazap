<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_message_templates', function (Blueprint $table): void {
            $table->uuid('chat_instance_id')->nullable()->after('tenant_id');
            $table->string('provider', 20)->default('local')->after('chat_instance_id');
            $table->string('external_id', 255)->nullable()->after('provider');
            $table->string('language', 10)->default('pt_BR')->after('external_id');
            $table->string('status', 20)->default('approved')->after('language');
            $table->text('rejected_reason')->nullable()->after('status');
            $table->jsonb('components_json')->nullable()->after('rejected_reason');
            $table->timestamp('last_synced_at')->nullable()->after('components_json');

            $table->foreign('chat_instance_id')
                ->references('id')
                ->on('chat_instances')
                ->cascadeOnDelete();

            $table->index(['chat_instance_id', 'status'], 'idx_msg_templates_instance_status');
        });

        DB::statement(
            'CREATE UNIQUE INDEX uniq_msg_templates_instance_name_lang '
            .'ON chat_message_templates (chat_instance_id, name, language) '
            .'WHERE chat_instance_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uniq_msg_templates_instance_name_lang');

        Schema::table('chat_message_templates', function (Blueprint $table): void {
            $table->dropIndex('idx_msg_templates_instance_status');
            $table->dropForeign(['chat_instance_id']);
            $table->dropColumn([
                'chat_instance_id',
                'provider',
                'external_id',
                'language',
                'status',
                'rejected_reason',
                'components_json',
                'last_synced_at',
            ]);
        });
    }
};
