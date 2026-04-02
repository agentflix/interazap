<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('title');
            $table->string('type')->default('meeting');
            $table->string('status')->default('scheduled');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('recurrence')->default('none');
            $table->timestamp('recurrence_ends_at')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'starts_at']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('crm_event_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_event_id')->constrained('crm_events')->cascadeOnDelete();
            $table->uuidMorphs('linkable');
            $table->timestamps();
        });

        Schema::create('crm_event_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_event_id')->constrained('crm_events')->cascadeOnDelete();
            $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->foreignUuid('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_organizer')->default(false);
            $table->timestamps();
        });

        Schema::create('crm_event_reminders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_event_id')->constrained('crm_events')->cascadeOnDelete();
            $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('type')->default('notification');
            $table->integer('minutes_before')->default(0);
            $table->boolean('notify_ui')->default(true);
            $table->boolean('notify_email')->default(false);
            $table->boolean('notify_push')->default(false);
            $table->boolean('notify_whatsapp')->default(false);
            $table->boolean('notify_webhook')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_event_reminders');
        Schema::dropIfExists('crm_event_participants');
        Schema::dropIfExists('crm_event_links');
        Schema::dropIfExists('crm_events');
    }
};
