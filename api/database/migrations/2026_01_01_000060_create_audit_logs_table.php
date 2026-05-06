<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela de audit logs para auditoria de mudanças em entidades do sistema.
 *
 * Tabela criada:
 * - audit_logs: Registro imutável de eventos de criação, atualização e exclusão
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do log de auditoria (UUID)');
            $table->uuid('tenant_id')->nullable()->comment('Tenant onde o evento ocorreu');
            $table->uuid('user_id')->nullable()->comment('Usuário que executou a ação');
            $table->string('user_type', 255)->nullable()->comment('Tipo do usuário (Morph)');
            $table->string('event', 100)->comment('Tipo de evento (created/updated/deleted)');
            $table->string('auditable_type', 255)->comment('Tipo da entidade auditada (Morph)');
            $table->string('auditable_id', 255)->comment('ID da entidade auditada (Morph)');
            $table->jsonb('old_values')->nullable()->comment('Valores anteriores da entidade');
            $table->jsonb('new_values')->nullable()->comment('Valores novos da entidade');
            $table->text('url')->nullable()->comment('URL da requisição que gerou o evento');
            $table->ipAddress('ip_address')->nullable()->comment('Endereço IP do usuário (IPv4/IPv6)');
            $table->string('user_agent', 1023)->nullable()->comment('User-Agent da requisição');
            $table->string('tags', 255)->nullable()->comment('Tags da auditoria para categorização');
            $table->timestamps(0);

            $table->foreign('tenant_id', 'fk_audit_logs_tenant_id')
                ->references('id')
                ->on('platform_tenants');
            $table->foreign('user_id', 'fk_audit_logs_user_id')
                ->references('id')
                ->on('auth_users');
            $table->index(['tenant_id', 'created_at'], 'idx_audit_logs_tenant_created');
            $table->index(['tenant_id', 'event'], 'idx_audit_logs_tenant_event');
            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_logs_auditable_morph');
            $table->index(['user_id', 'created_at'], 'idx_audit_logs_user_created');
            $table->index(['event', 'created_at'], 'idx_audit_logs_event_created');
            $table->index(['ip_address'], 'idx_audit_logs_ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
