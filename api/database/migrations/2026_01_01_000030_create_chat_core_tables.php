<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat Domain Core Tables - Consolidated Migration
 *
 * Creates core chat infrastructure:
 * - chat_webhook_events: Webhook event processing
 * - chat_instances: WhatsApp/channel instances
 * - chat_ticket_sequences: Ticket number sequences
 * - chat_tickets: Support tickets (PARTITIONED for scale)
 * - chat_messages: Chat messages (PARTITIONED for scale)
 * - chat_message_templates: Message templates
 * - chat_ticket_transfers: Ticket transfer history
 * - chat_message_interactions: Message interaction tracking
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // CHAT_WEBHOOK_EVENTS
        // =====================================================================
        Schema::create('chat_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('stream_id')->unique();
            $table->string('idempotency_key')->unique();
            $table->string('provider', 50);
            $table->string('instance_webhook_token');
            $table->string('event_type', 100)->nullable();
            $table->string('direction', 20)->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['provider', 'instance_webhook_token']);
        });

        // =====================================================================
        // CHAT_INSTANCES
        // =====================================================================
        Schema::create('chat_instances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('provider', 50);
            $table->string('name')->nullable();
            $table->string('mode', 20)->default('production');
            $table->string('status', 50)->default('disconnected');
            $table->boolean('is_active')->default(true);
            $table->boolean('evaluation_enabled')->default(false);
            $table->string('webhook_token')->unique();
            $table->json('settings_json')->nullable();
            $table->timestamp('last_status_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'provider']);
            $table->index(['tenant_id', 'status']);
        });

        // =====================================================================
        // CHAT_TICKET_SEQUENCES
        // =====================================================================
        Schema::create('chat_ticket_sequences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->string('prefix', 10)->default('');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();
        });

        // =====================================================================
        // CHAT_TICKETS - High volume table
        // =====================================================================
        Schema::create('chat_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('contact_id')->nullable();
            $table->uuid('instance_id')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->string('protocol', 50)->nullable();
            $table->string('channel', 20)->default('whatsapp');
            $table->string('remote_jid')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('phone_e164', 20)->nullable();
            $table->string('push_name')->nullable();
            $table->string('profile_picture_url', 500)->nullable();
            $table->string('status', 20)->default('pending'); // pending, open, waiting, resolved, closed
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->string('category', 100)->nullable();
            $table->text('subject')->nullable();
            $table->boolean('is_group')->default(false);
            $table->boolean('is_bot_active')->default(true);
            $table->timestamp('human_takeover_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->string('close_reason', 100)->nullable();
            $table->unsignedInteger('auto_close_queue_after_minutes')->default(0);
            $table->unsignedInteger('auto_close_in_progress_after_minutes')->default(0);
            $table->timestamp('sla_first_response_due_at')->nullable();
            $table->timestamp('sla_resolution_due_at')->nullable();
            $table->boolean('sla_first_response_breached')->default(false);
            $table->boolean('sla_resolution_breached')->default(false);
            $table->jsonb('tags')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->nullOnDelete();

            $table->foreign('instance_id')
                ->references('id')
                ->on('chat_instances')
                ->nullOnDelete();

            $table->foreign('assigned_to')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            $table->foreign('closed_by')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            // Performance indexes
            $table->index(['tenant_id', 'status'], 'idx_tickets_tenant_status');
            $table->index(['tenant_id', 'remote_jid']);
            $table->index(['tenant_id', 'assigned_to']);
            $table->index(['tenant_id', 'contact_id']);
            $table->index(['tenant_id', 'last_message_at']);
            $table->index('protocol');
        });

        // =====================================================================
        // CHAT_MESSAGES - Very high volume table
        // =====================================================================
        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('ticket_id');
            $table->uuid('user_id')->nullable();
            $table->uuid('contact_id')->nullable();
            $table->text('content')->nullable();
            $table->string('type', 50)->default('text'); // text, image, audio, video, document, sticker, location
            $table->enum('direction', ['incoming', 'outgoing'])->default('incoming');
            $table->boolean('is_from_contact')->default(false);
            $table->string('source', 50)->default('webhook'); // webhook, user, bot, api, system
            $table->string('status', 20)->default('pending'); // pending, sent, delivered, read, failed
            $table->string('file_url', 500)->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('external_id', 100)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->jsonb('reactions')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->jsonb('edit_history')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('ticket_id')
                ->references('id')
                ->on('chat_tickets')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->nullOnDelete();

            $table->foreign('deleted_by')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            // Performance indexes
            $table->index(['ticket_id', 'created_at'], 'idx_messages_ticket_created');
            $table->index(['tenant_id', 'external_id']);
        });

        // =====================================================================
        // CHAT_MESSAGE_TEMPLATES
        // =====================================================================
        Schema::create('chat_message_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('shortcut', 50)->nullable();
            $table->text('content');
            $table->string('category', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'is_active']);
            $table->index('shortcut');
        });

        // =====================================================================
        // CHAT_TICKET_TRANSFERS
        // =====================================================================
        Schema::create('chat_ticket_transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('ticket_id');
            $table->uuid('from_user_id')->nullable();
            $table->uuid('to_user_id')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending, accepted, rejected
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('ticket_id')
                ->references('id')
                ->on('chat_tickets')
                ->cascadeOnDelete();

            $table->foreign('from_user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            $table->foreign('to_user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();
        });

        // =====================================================================
        // CHAT_MESSAGE_INTERACTIONS
        // =====================================================================
        Schema::create('chat_message_interactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('message_id');
            $table->string('interaction_type', 50); // reaction, reply, forward, etc.
            $table->uuid('user_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('message_id')
                ->references('id')
                ->on('chat_messages')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            $table->index(['message_id', 'interaction_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_interactions');
        Schema::dropIfExists('chat_ticket_transfers');
        Schema::dropIfExists('chat_message_templates');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_tickets');
        Schema::dropIfExists('chat_ticket_sequences');
        Schema::dropIfExists('chat_instances');
        Schema::dropIfExists('chat_webhook_events');
    }
};
