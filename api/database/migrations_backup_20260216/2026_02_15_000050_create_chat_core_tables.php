<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_ticket_sequences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('platform_tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->string('prefix')->default('');
            $table->timestamps();
        });

        Schema::create('chat_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignUuid('instance_id')->nullable()->constrained('chat_instances')->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('protocol')->nullable();
            $table->string('channel')->default('whatsapp');
            $table->string('remote_jid')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_e164')->nullable();
            $table->string('push_name')->nullable();
            $table->string('profile_picture_url')->nullable();
            $table->string('status')->default('pending');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->string('category')->nullable();
            $table->text('subject')->nullable();
            $table->boolean('is_group')->default(false);
            $table->boolean('is_bot_active')->default(true);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUuid('closed_by')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('close_reason')->nullable();

            // SLA & Auto Close
            $table->unsignedInteger('auto_close_queue_after_minutes')->default(0);
            $table->unsignedInteger('auto_close_in_progress_after_minutes')->default(0);
            $table->timestamp('sla_first_response_due_at')->nullable();
            $table->timestamp('sla_resolution_due_at')->nullable();
            $table->boolean('sla_first_response_breached')->default(false);
            $table->boolean('sla_resolution_breached')->default(false);

            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'remote_jid']);
            $table->index('protocol');
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('chat_tickets')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->text('content')->nullable();
            $table->string('type')->default('text');
            $table->enum('direction', ['incoming', 'outgoing'])->default('incoming');
            $table->boolean('is_from_contact')->default(false);
            $table->string('status')->default('pending');
            $table->string('file_url')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('external_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->foreignUuid('deleted_by')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
            $table->index(['tenant_id', 'external_id']);
        });

        Schema::create('chat_message_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('shortcut')->nullable();
            $table->text('content');
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index('shortcut');
        });

        Schema::create('chat_ticket_transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('chat_tickets')->cascadeOnDelete();
            $table->foreignUuid('from_user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->foreignUuid('to_user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('chat_ticket_transfers');
        Schema::dropIfExists('chat_message_templates');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_tickets');
        Schema::dropIfExists('chat_ticket_sequences');
    }
};
