<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('auth_users')->cascadeOnDelete();
            $table->string('notification_type', 50);
            $table->json('channels')->default('["ui"]');
            $table->boolean('enabled')->default(true);
            $table->time('quiet_start')->nullable();
            $table->time('quiet_end')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'notification_type']);
            $table->index(['tenant_id', 'notification_type']);
        });

        Schema::create('configuration_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->string('channel', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('configuration_notification_webhooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('secret')->nullable();
            $table->json('event_types')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('failure_count')->default(0);
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_notification_webhooks');
        Schema::dropIfExists('configuration_notifications');
        Schema::dropIfExists('configuration_notification_preferences');
    }
};
