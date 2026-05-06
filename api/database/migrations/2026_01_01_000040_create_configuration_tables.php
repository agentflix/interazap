<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas do contexto Configuration para gerenciamento de
 * horários de funcionamento, preferências de notificação, notificações
 * internas, webhooks de notificação e subscriptions push.
 *
 * Tabelas criadas:
 * - configuration_opening_hours: Horários de funcionamento por dia da semana
 * - configuration_notification_preferences: Preferências de notificação por usuário
 * - configuration_notifications: Notificações internas do sistema
 * - configuration_notification_webhooks: Webhooks para eventos externos
 * - configuration_push_subscriptions: Subscriptions push para notificações em tempo real
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuration_opening_hours')) {
            Schema::create('configuration_opening_hours', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do horário');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->smallInteger('day_of_week')->comment('Dia da semana (0=Domingo, 6=Sábado)');
                $table->time('open_time', 0)->comment('Horário de abertura');
                $table->time('close_time', 0)->comment('Horário de fechamento');
                $table->boolean('is_active')->default(true)->comment('Se o horário está ativo');
                $table->timestamps();

                $table->index('tenant_id', 'idx_configuration_opening_hours_tenant_id');
                $table->index('day_of_week', 'idx_configuration_opening_hours_day_of_week');
                $table->index('is_active', 'idx_configuration_opening_hours_is_active');

                $table->foreign('tenant_id', 'fk_configuration_opening_hours_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('configuration_notification_preferences')) {
            Schema::create('configuration_notification_preferences', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da preferência');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('user_id')->comment('Usuário vinculado');
                $table->string('notification_type', 50)->comment('Tipo de notificação (ticket_assigned/message_received)');
                $table->json('channels')->default(json_encode(['ui']))->comment('Canais habilitados (email/push/sms)');
                $table->boolean('enabled')->default(true)->comment('Se a preferência está ativa');
                $table->time('quiet_start')->nullable()->comment('Início do modo silencioso');
                $table->time('quiet_end')->nullable()->comment('Fim do modo silencioso');
                $table->timestamps();

                $table->index('tenant_id', 'idx_configuration_notification_preferences_tenant_id');
                $table->index('user_id', 'idx_configuration_notification_preferences_user_id');
                $table->index('notification_type', 'idx_configuration_notification_preferences_type');
                $table->index('enabled', 'idx_configuration_notification_preferences_enabled');

                $table->foreign('tenant_id', 'fk_configuration_notification_preferences_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('user_id', 'fk_configuration_notification_preferences_user_id')
                    ->references('id')
                    ->on('auth_users')
                    ->onDelete('cascade');
            });

            DB::statement('ALTER TABLE configuration_notification_preferences ALTER COLUMN channels TYPE json');
        }

        if (! Schema::hasTable('configuration_notifications')) {
            Schema::create('configuration_notifications', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da notificação');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('user_id')->nullable()->comment('Destinatário da notificação');
                $table->string('type', 50)->comment('Tipo de notificação');
                $table->string('title', 255)->comment('Título da notificação');
                $table->text('body')->nullable()->comment('Conteúdo da notificação');
                $table->json('data')->nullable()->comment('Dados adicionais da notificação');
                $table->string('channel', 20)->default('database')->comment('Canal de envio (database/email/push/sms)');
                $table->string('status', 20)->default('pending')->comment('Status (pending/sent/read/failed)');
                $table->timestamp('sent_at')->nullable()->comment('Quando foi enviada');
                $table->timestamp('read_at')->nullable()->comment('Quando foi lida');
                $table->string('error_message', 255)->nullable()->comment('Erro no envio');
                $table->timestamps();

                $table->index('tenant_id', 'idx_configuration_notifications_tenant_id');
                $table->index('user_id', 'idx_configuration_notifications_user_id');
                $table->index('type', 'idx_configuration_notifications_type');
                $table->index('status', 'idx_configuration_notifications_status');
                $table->index('channel', 'idx_configuration_notifications_channel');
                $table->index('sent_at', 'idx_configuration_notifications_sent_at');
                $table->index('read_at', 'idx_configuration_notifications_read_at');

                $table->foreign('tenant_id', 'fk_configuration_notifications_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('user_id', 'fk_configuration_notifications_user_id')
                    ->references('id')
                    ->on('auth_users')
                    ->onDelete('cascade');
            });

            DB::statement('ALTER TABLE configuration_notifications ALTER COLUMN data TYPE json');
        }

        if (! Schema::hasTable('configuration_notification_webhooks')) {
            Schema::create('configuration_notification_webhooks', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do webhook');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->string('name', 255)->comment('Nome descritivo do webhook');
                $table->string('url', 255)->comment('URL do webhook');
                $table->string('secret', 255)->nullable()->comment('Secret para validação HMAC');
                $table->json('event_types')->nullable()->comment('Tipos de eventos que disparam o webhook');
                $table->boolean('is_active')->default(true)->comment('Se o webhook está ativo');
                $table->integer('failure_count')->default(0)->comment('Contador de falhas consecutivas');
                $table->timestamp('last_failure_at')->nullable()->comment('Data da última falha');
                $table->timestamp('last_success_at')->nullable()->comment('Data do último sucesso');
                $table->timestamps();

                $table->index('tenant_id', 'idx_configuration_notification_webhooks_tenant_id');
                $table->index('is_active', 'idx_configuration_notification_webhooks_is_active');
                $table->index('failure_count', 'idx_configuration_notification_webhooks_failure_count');
                $table->index('last_failure_at', 'idx_configuration_notification_webhooks_last_failure_at');
                $table->index('last_success_at', 'idx_configuration_notification_webhooks_last_success_at');

                $table->foreign('tenant_id', 'fk_configuration_notification_webhooks_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
            });

            DB::statement('ALTER TABLE configuration_notification_webhooks ALTER COLUMN event_types TYPE json');
        }

        if (! Schema::hasTable('configuration_push_subscriptions')) {
            Schema::create('configuration_push_subscriptions', static function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da subscription');
                $table->uuid('tenant_id')->comment('Tenant proprietário');
                $table->uuid('user_id')->comment('Usuário vinculado');
                $table->string('endpoint', 1000)->comment('URL do endpoint push');
                $table->string('p256dh', 255)->comment('Chave pública P-256');
                $table->string('auth', 255)->comment('Auth secret');
                $table->string('content_encoding', 20)->default('aes128gcm')->comment('Codificação de conteúdo');
                $table->boolean('is_active')->default(true)->comment('Se a subscription está ativa');
                $table->timestamp('last_seen_at')->nullable()->comment('Última atividade detectada');
                $table->timestamps();

                $table->index('tenant_id', 'idx_configuration_push_subscriptions_tenant_id');
                $table->index('user_id', 'idx_configuration_push_subscriptions_user_id');
                $table->index('is_active', 'idx_configuration_push_subscriptions_is_active');
                $table->index('last_seen_at', 'idx_configuration_push_subscriptions_last_seen_at');

                $table->foreign('tenant_id', 'fk_configuration_push_subscriptions_tenant_id')
                    ->references('id')
                    ->on('platform_tenants')
                    ->onDelete('cascade');
                $table->foreign('user_id', 'fk_configuration_push_subscriptions_user_id')
                    ->references('id')
                    ->on('auth_users')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_push_subscriptions');
        Schema::dropIfExists('configuration_notification_webhooks');
        Schema::dropIfExists('configuration_notifications');
        Schema::dropIfExists('configuration_notification_preferences');
        Schema::dropIfExists('configuration_opening_hours');
    }
};
