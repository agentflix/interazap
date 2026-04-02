<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration Domain Tables - Consolidated Migration
 *
 * Creates configuration entities:
 * - configuration_opening_hours: Business hours
 * - configuration_notification_preferences: User notification settings
 * - configuration_notifications: Notification records
 * - configuration_notification_webhooks: Webhook configurations
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // CONFIGURATION_OPENING_HOURS
        // =====================================================================
        Schema::create('configuration_opening_hours', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday, 6=Saturday
            $table->time('open_time');
            $table->time('close_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'day_of_week']);
            $table->index(['tenant_id', 'is_active']);
        });

        // =====================================================================
        // CONFIGURATION_NOTIFICATION_PREFERENCES
        // =====================================================================
        Schema::create('configuration_notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('notification_type', 50);
            $table->json('channels')->default('["ui"]');
            $table->boolean('enabled')->default(true);
            $table->time('quiet_start')->nullable();
            $table->time('quiet_end')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('auth_users')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'notification_type']);
            $table->index(['tenant_id', 'notification_type']);
        });

        // =====================================================================
        // CONFIGURATION_NOTIFICATIONS
        // =====================================================================
        Schema::create('configuration_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable();
            $table->string('type', 50);
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->string('channel', 20);
            $table->string('status', 20)->default('pending'); // pending, sent, delivered, read, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        // =====================================================================
        // CONFIGURATION_NOTIFICATION_WEBHOOKS
        // =====================================================================
        Schema::create('configuration_notification_webhooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('url', 500);
            $table->string('secret')->nullable();
            $table->json('event_types')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('failure_count')->default(0);
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_notification_webhooks');
        Schema::dropIfExists('configuration_notifications');
        Schema::dropIfExists('configuration_notification_preferences');
        Schema::dropIfExists('configuration_opening_hours');
    }
};
