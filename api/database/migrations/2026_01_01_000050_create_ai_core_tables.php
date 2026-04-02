<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Domain Core Tables - Consolidated Migration
 *
 * Creates AI automation infrastructure:
 * - ai_autopilot_playbooks: Automation playbooks
 * - ai_autopilot_tools: Available tools for AI
 * - ai_autopilot_guardrails: Safety guardrails
 * - ai_autopilot_runs: Execution runs
 * - ai_autopilot_actions: Actions within runs
 * - ai_autopilot_approvals: Human-in-the-loop approvals
 * - ai_model_pricings: LLM pricing reference
 * - ai_usage_logs: Token usage tracking
 * - ai_seller_notifications: Sales AI notifications
 * - ai_post_sale_schedules: Post-sale follow-up scheduling
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // AI_AUTOPILOT_PLAYBOOKS
        // =====================================================================
        Schema::create('ai_autopilot_playbooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->json('steps')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'is_active']);
        });

        // =====================================================================
        // AI_AUTOPILOT_TOOLS
        // =====================================================================
        Schema::create('ai_autopilot_tools', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->json('parameters_schema')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'name']);
        });

        // =====================================================================
        // AI_AUTOPILOT_GUARDRAILS
        // =====================================================================
        Schema::create('ai_autopilot_guardrails', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('rule_type', 50)->default('LOG_ONLY'); // LOG_ONLY, WARN, BLOCK
            $table->json('conditions')->nullable();
            $table->unsignedInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'is_active']);
        });

        // =====================================================================
        // AI_AUTOPILOT_RUNS
        // =====================================================================
        Schema::create('ai_autopilot_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('playbook_id');
            $table->string('status', 20)->default('running'); // running, completed, failed, cancelled
            $table->unsignedInteger('playbook_version')->default(1);
            $table->json('input_context')->nullable();
            $table->json('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('playbook_id')
                ->references('id')
                ->on('ai_autopilot_playbooks')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'status']);
        });

        // =====================================================================
        // AI_AUTOPILOT_ACTIONS
        // =====================================================================
        Schema::create('ai_autopilot_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->string('action_type', 100);
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->string('guardrail_result', 50)->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->foreign('run_id')
                ->references('id')
                ->on('ai_autopilot_runs')
                ->cascadeOnDelete();

            $table->index(['run_id', 'order']);
        });

        // =====================================================================
        // AI_AUTOPILOT_APPROVALS
        // =====================================================================
        Schema::create('ai_autopilot_approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->json('requested_action')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();

            $table->foreign('run_id')
                ->references('id')
                ->on('ai_autopilot_runs')
                ->cascadeOnDelete();

            $table->foreign('approved_by')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            $table->index(['run_id', 'status']);
        });

        // =====================================================================
        // AI_MODEL_PRICINGS - Reference table (no tenant)
        // =====================================================================
        Schema::create('ai_model_pricings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 50);
            $table->string('model_name', 100);
            $table->string('display_name', 150)->nullable();
            $table->decimal('input_cost_per_1m', 10, 4)->default(0);
            $table->decimal('output_cost_per_1m', 10, 4)->default(0);
            $table->unsignedInteger('max_context_tokens')->nullable();
            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('pricing_effective_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'model_name']);
            $table->index(['is_active', 'provider']);
        });

        // =====================================================================
        // AI_USAGE_LOGS - High volume, consider partitioning
        // =====================================================================
        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable();
            $table->uuid('ai_model_pricing_id')->nullable();
            $table->string('model_name', 100);
            $table->string('provider', 50)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('input_cost', 12, 6)->default(0);
            $table->decimal('output_cost', 12, 6)->default(0);
            $table->string('request_id', 100)->nullable();
            $table->string('feature', 100)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('usable_type')->nullable();
            $table->uuid('usable_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();

            $table->foreign('ai_model_pricing_id')
                ->references('id')
                ->on('ai_model_pricings')
                ->nullOnDelete();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['model_name', 'created_at']);
            $table->index('created_at'); // For LGPD cleanup
        });

        // =====================================================================
        // AI_SELLER_NOTIFICATIONS
        // =====================================================================
        Schema::create('ai_seller_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('seller_id');
            $table->string('notifiable_type')->nullable();
            $table->uuid('notifiable_id')->nullable();
            $table->text('message');
            $table->string('reason', 50);
            $table->string('channel', 20)->default('email');
            $table->string('priority', 20)->default('normal'); // low, normal, high, urgent
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('seller_id')
                ->references('id')
                ->on('auth_users')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'delivered_at', 'failed_at']);
            $table->index(['seller_id', 'created_at']);
            $table->index(['priority', 'scheduled_at']);
        });

        // =====================================================================
        // AI_POST_SALE_SCHEDULES
        // =====================================================================
        Schema::create('ai_post_sale_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('negotiation_id');
            $table->uuid('ticket_id')->nullable();
            $table->string('schedule_type', 20); // thank_you, follow_up, nps, etc.
            $table->timestamp('sale_date');
            $table->timestamp('scheduled_at');
            $table->string('status', 20)->default('pending'); // pending, sent, failed, cancelled
            $table->timestamp('sent_at')->nullable();
            $table->string('message_id', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('custom_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('negotiation_id')
                ->references('id')
                ->on('crm_negotiations')
                ->cascadeOnDelete();

            $table->foreign('ticket_id')
                ->references('id')
                ->on('chat_tickets')
                ->nullOnDelete();

            $table->index(['tenant_id', 'status', 'scheduled_at']);
            $table->index(['negotiation_id', 'schedule_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_post_sale_schedules');
        Schema::dropIfExists('ai_seller_notifications');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_model_pricings');
        Schema::dropIfExists('ai_autopilot_approvals');
        Schema::dropIfExists('ai_autopilot_actions');
        Schema::dropIfExists('ai_autopilot_runs');
        Schema::dropIfExists('ai_autopilot_guardrails');
        Schema::dropIfExists('ai_autopilot_tools');
        Schema::dropIfExists('ai_autopilot_playbooks');
    }
};
