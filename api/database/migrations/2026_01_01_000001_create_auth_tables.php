<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas do contexto Auth (usuários, sessões, tokens, RBAC via Spatie).
 *
 * Tabelas criadas:
 * - auth_permissions
 * - auth_roles
 * - auth_users
 * - auth_password_reset_tokens
 * - auth_sessions
 * - auth_personal_access_tokens
 * - auth_model_has_permissions
 * - auth_model_has_roles
 * - auth_role_has_permissions
 * - auth_device_tokens
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('auth_permissions')) {
            Schema::create('auth_permissions', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da permissão');
                $table->string('name', 255)->unique()->comment('Nome canônico da permissão (ex: users.create)');
                $table->string('guard_name', 255)->default('web')->comment('Guard de autenticação a qual a permissão pertence');
                $table->timestamps();

                $table->index('name', 'idx_auth_permissions_name');
            });
        }

        if (!Schema::hasTable('auth_roles')) {
            Schema::create('auth_roles', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único da role');
                $table->string('name', 255)->unique()->comment('Nome canônico da role (ex: admin, manager)');
                $table->string('guard_name', 255)->default('web')->comment('Guard de autenticação a qual a role pertence');
                $table->timestamps();

                $table->index('name', 'idx_auth_roles_name');
            });
        }

        if (!Schema::hasTable('auth_users')) {
            Schema::create('auth_users', function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador único do usuário');
            $table->uuid('tenant_id')->comment('Tenant ao qual o usuário pertence');
            $table->string('name', 255)->comment('Nome completo do usuário');
            $table->string('email', 255)->unique()->comment('E-mail único para login');
            $table->string('phone', 255)->nullable()->comment('Telefone de contato');
            $table->string('avatar_url', 255)->nullable()->comment('URL pública do avatar do usuário');
            $table->timestamp('email_verified_at', 0)->nullable()->comment('Quando o e-mail foi verificado');
            $table->string('password', 255)->comment('Hash da senha');
            $table->string('remember_token', 100)->nullable()->comment('Token para lembrar sessão');
            $table->boolean('is_active')->default(true)->comment('Se o usuário pode acessar a plataforma');
            $table->boolean('two_factor_enabled')->default(false)->comment('Se o 2FA está habilitado');
            $table->text('two_factor_secret')->nullable()->comment('Segredo TOTP para 2FA');
            $table->text('two_factor_recovery_codes')->nullable()->comment('Códigos de recuperação do 2FA');
            $table->jsonb('preferences')->default('{}')->comment('Preferências do usuário (tema, notificações, etc.)');
            $table->timestamps(0);
            $table->softDeletes();

                $table->index('tenant_id', 'idx_auth_users_tenant_id');
                $table->index('email', 'idx_auth_users_email');
                $table->index('is_active', 'idx_auth_users_is_active');

                $table->foreign('tenant_id')->references('id')->on('platform_tenants')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('auth_password_reset_tokens')) {
            Schema::create('auth_password_reset_tokens', function (Blueprint $table): void {
                $table->string('email', 255)->primary()->comment('E-mail para o qual o token de reset foi emitido');
                $table->string('token', 255)->comment('Token criptografado de reset de senha');
                $table->timestamp('created_at')->nullable()->comment('Quando o token foi criado');
            });
        }

        if (!Schema::hasTable('auth_sessions')) {
            Schema::create('auth_sessions', function (Blueprint $table): void {
                $table->string('id', 128)->primary()->comment('ID único da sessão');
                $table->uuid('user_id')->nullable()->comment('Usuário dono da sessão (FK -> auth_users)');
                $table->string('ip_address', 45)->nullable()->comment('Endereço IP da sessão');
                $table->text('user_agent')->nullable()->comment('User agent do cliente');
                $table->longText('payload')->comment('Payload serializado da sessão');
                $table->integer('last_activity')->comment('Timestamp da última atividade');
            });
        }

        if (!Schema::hasTable('auth_personal_access_tokens')) {
            Schema::create('auth_personal_access_tokens', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador sequencial do token');
                $table->string('tokenable_type', 255)->comment('Tipo do modelo dono do token (polymorphic)');
                $table->uuid('tokenable_id')->comment('ID do modelo dono do token (polymorphic)');
                $table->string('name', 255)->comment('Nome descritivo do token');
                $table->string('token', 64)->unique()->comment('Hash do token para validação');
                $table->text('abilities')->nullable()->comment('Habilidades/escopos concedidos ao token');
                $table->timestamp('last_used_at', 0)->nullable()->comment('Último uso do token');
                $table->timestamp('expires_at', 0)->nullable()->comment('Data de expiração do token');
                $table->timestamps(0);

                $table->index(['tokenable_type', 'tokenable_id'], 'idx_auth_pat_tokenable');
            });
        }

        if (!Schema::hasTable('auth_model_has_permissions')) {
            Schema::create('auth_model_has_permissions', function (Blueprint $table): void {
                $table->uuid('permission_id')->comment('Permissão atribuída (FK -> auth_permissions)');
                $table->string('model_type', 255)->comment('Tipo do modelo (classe PHP)');
                $table->uuid('model_id')->comment('ID do modelo (polymorphic)');
                $table->comment('Tabela pivô entre permissões e modelos (polymorphic)');

                $table->primary(['permission_id', 'model_id', 'model_type'], 'pk_auth_model_has_permissions');

                $table->index(['model_id', 'model_type'], 'idx_auth_mhp_model');

                $table->foreign('permission_id')->references('id')->on('auth_permissions')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('auth_model_has_roles')) {
            Schema::create('auth_model_has_roles', function (Blueprint $table): void {
                $table->uuid('role_id')->comment('Role atribuída (FK -> auth_roles)');
                $table->string('model_type', 255)->comment('Tipo do modelo (classe PHP)');
                $table->uuid('model_id')->comment('ID do modelo (polymorphic)');
                $table->comment('Tabela pivô entre roles e modelos (polymorphic)');

                $table->primary(['role_id', 'model_id', 'model_type'], 'pk_auth_model_has_roles');

                $table->index(['model_id', 'model_type'], 'idx_auth_mhr_model');

                $table->foreign('role_id')->references('id')->on('auth_roles')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('auth_role_has_permissions')) {
            Schema::create('auth_role_has_permissions', function (Blueprint $table): void {
                $table->uuid('permission_id')->comment('Permissão vinculada (FK -> auth_permissions)');
                $table->uuid('role_id')->comment('Role dona da permissão (FK -> auth_roles)');
                $table->comment('Tabela pivô entre roles e permissões');

                $table->primary(['permission_id', 'role_id'], 'pk_auth_role_has_permissions');

                $table->foreign('permission_id')->references('id')->on('auth_permissions')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('auth_roles')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('auth_device_tokens')) {
            Schema::create('auth_device_tokens', function (Blueprint $table): void {
                $table->uuid('id')->primary()->comment('Identificador único do token de dispositivo');
                $table->uuid('tenant_id')->comment('Tenant de origem (FK -> platform_tenants)');
                $table->uuid('user_id')->comment('Usuário dono do dispositivo (FK -> auth_users)');
                $table->string('platform', 20)->comment('Plataforma do dispositivo: ios, android, web');
                $table->text('token')->comment('Token de push notification do dispositivo');
                $table->string('device_name', 255)->nullable()->comment('Nome descritivo do dispositivo');
                $table->timestamp('last_active_at')->nullable()->comment('Última atividade registrada');
                $table->timestamp('revoked_at')->nullable()->comment('Quando o token foi revogado');
                $table->timestamps();

                $table->index('tenant_id', 'idx_auth_device_tokens_tenant_id');
                $table->index('user_id', 'idx_auth_device_tokens_user_id');
                $table->index('platform', 'idx_auth_device_tokens_platform');

                $table->foreign('tenant_id')->references('id')->on('platform_tenants')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('auth_users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_device_tokens');
        Schema::dropIfExists('auth_role_has_permissions');
        Schema::dropIfExists('auth_model_has_roles');
        Schema::dropIfExists('auth_model_has_permissions');
        Schema::dropIfExists('auth_personal_access_tokens');
        Schema::dropIfExists('auth_sessions');
        Schema::dropIfExists('auth_password_reset_tokens');
        Schema::dropIfExists('auth_users');
        Schema::dropIfExists('auth_roles');
        Schema::dropIfExists('auth_permissions');
    }
};
