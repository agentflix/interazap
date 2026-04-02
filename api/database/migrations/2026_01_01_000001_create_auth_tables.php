<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable(false)->constrained('platform_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('avatar_url')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->boolean('is_active')->default(true);
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('auth_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('auth_sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('auth_personal_access_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('auth_permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('auth_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('auth_model_has_permissions', function (Blueprint $table): void {
            $table->uuid('permission_id');
            $table->string('model_type');
            $table->uuid('model_id');

            $table->index(['model_id', 'model_type']);
            $table->foreign('permission_id')->references('id')->on('auth_permissions')->onDelete('cascade');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('auth_model_has_roles', function (Blueprint $table): void {
            $table->uuid('role_id');
            $table->string('model_type');
            $table->uuid('model_id');

            $table->index(['model_id', 'model_type']);
            $table->foreign('role_id')->references('id')->on('auth_roles')->onDelete('cascade');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('auth_role_has_permissions', function (Blueprint $table): void {
            $table->uuid('permission_id');
            $table->uuid('role_id');

            $table->foreign('permission_id')->references('id')->on('auth_permissions')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('auth_roles')->onDelete('cascade');
            $table->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_role_has_permissions');
        Schema::dropIfExists('auth_model_has_roles');
        Schema::dropIfExists('auth_model_has_permissions');
        Schema::dropIfExists('auth_roles');
        Schema::dropIfExists('auth_permissions');
        Schema::dropIfExists('auth_personal_access_tokens');
        Schema::dropIfExists('auth_sessions');
        Schema::dropIfExists('auth_password_reset_tokens');
        Schema::dropIfExists('auth_users');
    }
};
