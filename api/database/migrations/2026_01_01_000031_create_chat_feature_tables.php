<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat Domain Feature Tables - Consolidated Migration
 *
 * Creates chat feature entities:
 * - chat_chatbot_rules: Chatbot automation rules
 * - chat_chatbot_cooldowns: Rate limiting for chatbot
 * - chat_campaigns: Marketing campaigns
 * - chat_campaign_contacts: Campaign recipients
 * - chat_quick_answers: Quick reply templates
 * - chat_ticket_evaluations: Customer satisfaction
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // CHAT_CHATBOT_RULES
        // =====================================================================
        Schema::create('chat_chatbot_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('trigger_text');
            $table->text('response_text');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_welcome')->default(false);
            $table->unsignedInteger('cooldown_seconds')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'trigger_text']);
        });

        // =====================================================================
        // CHAT_CHATBOT_COOLDOWNS
        // =====================================================================
        Schema::create('chat_chatbot_cooldowns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('ticket_id');
            $table->uuid('rule_id');
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('ticket_id')
                ->references('id')
                ->on('chat_tickets')
                ->cascadeOnDelete();

            $table->foreign('rule_id')
                ->references('id')
                ->on('chat_chatbot_rules')
                ->cascadeOnDelete();

            $table->index(['ticket_id', 'rule_id', 'cooldown_until']);
        });

        // =====================================================================
        // CHAT_CAMPAIGNS
        // =====================================================================
        Schema::create('chat_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('instance_id')->nullable();
            $table->string('name');
            $table->text('message')->nullable();
            $table->jsonb('filter_criteria')->nullable();
            $table->string('status', 20)->default('draft'); // draft, scheduled, running, paused, completed, cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('instance_id')
                ->references('id')
                ->on('chat_instances')
                ->nullOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'scheduled_at']);
        });

        // =====================================================================
        // CHAT_CAMPAIGN_CONTACTS
        // =====================================================================
        Schema::create('chat_campaign_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('campaign_id');
            $table->uuid('contact_id');
            $table->string('status', 20)->default('pending'); // pending, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('campaign_id')
                ->references('id')
                ->on('chat_campaigns')
                ->cascadeOnDelete();

            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->cascadeOnDelete();

            $table->unique(['campaign_id', 'contact_id']);
            $table->index('status');
        });

        // =====================================================================
        // CHAT_QUICK_ANSWERS
        // =====================================================================
        Schema::create('chat_quick_answers', function (Blueprint $table): void {
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
        // CHAT_TICKET_EVALUATIONS
        // =====================================================================
        Schema::create('chat_ticket_evaluations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('ticket_id');
            $table->string('token')->unique();
            $table->unsignedTinyInteger('rating')->default(0);
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('ticket_id')
                ->references('id')
                ->on('chat_tickets')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_ticket_evaluations');
        Schema::dropIfExists('chat_quick_answers');
        Schema::dropIfExists('chat_campaign_contacts');
        Schema::dropIfExists('chat_campaigns');
        Schema::dropIfExists('chat_chatbot_cooldowns');
        Schema::dropIfExists('chat_chatbot_rules');
    }
};
