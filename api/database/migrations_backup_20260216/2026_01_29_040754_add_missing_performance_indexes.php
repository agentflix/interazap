<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing performance indexes identified in audit.
 *
 * @see docs/AUDIT_REPORT_2026-01-29.md DATA-CRIT-001
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Indexes for chat_chatbot_rules
        if (Schema::hasTable('chat_chatbot_rules')) {
            Schema::table('chat_chatbot_rules', function (Blueprint $table): void {
                $table->index(['tenant_id', 'is_active'], 'idx_chatbot_rules_tenant_active');
                $table->index(['tenant_id', 'trigger_text'], 'idx_chatbot_rules_tenant_trigger');
            });
        }

        // Indexes for crm_negotiation_files
        if (Schema::hasTable('crm_negotiation_files')) {
            Schema::table('crm_negotiation_files', function (Blueprint $table): void {
                $table->index(['tenant_id', 'crm_negotiation_id'], 'idx_neg_files_tenant_negotiation');
            });
        }

        // Indexes for crm_negotiation_tasks
        if (Schema::hasTable('crm_negotiation_tasks')) {
            Schema::table('crm_negotiation_tasks', function (Blueprint $table): void {
                $table->index(['tenant_id', 'status'], 'idx_neg_tasks_tenant_status');
                $table->index(['tenant_id', 'due_date'], 'idx_neg_tasks_tenant_due_date');
            });
        }

        // Indexes for chat_campaigns
        if (Schema::hasTable('chat_campaigns')) {
            Schema::table('chat_campaigns', function (Blueprint $table): void {
                $table->index(['tenant_id', 'status'], 'idx_campaigns_tenant_status');
                $table->index(['tenant_id', 'scheduled_at'], 'idx_campaigns_tenant_scheduled');
            });
        }

        // Functional index for ai_usage_logs date aggregation
        if (Schema::hasTable('ai_usage_logs')) {
            Schema::table('ai_usage_logs', function (Blueprint $table): void {
                $table->index(['tenant_id', 'created_at'], 'idx_usage_logs_tenant_created');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('chat_chatbot_rules')) {
            Schema::table('chat_chatbot_rules', function (Blueprint $table): void {
                $table->dropIndex('idx_chatbot_rules_tenant_active');
                $table->dropIndex('idx_chatbot_rules_tenant_trigger');
            });
        }

        if (Schema::hasTable('crm_negotiation_files')) {
            Schema::table('crm_negotiation_files', function (Blueprint $table): void {
                $table->dropIndex('idx_neg_files_tenant_negotiation');
            });
        }

        if (Schema::hasTable('crm_negotiation_tasks')) {
            Schema::table('crm_negotiation_tasks', function (Blueprint $table): void {
                $table->dropIndex('idx_neg_tasks_tenant_status');
                $table->dropIndex('idx_neg_tasks_tenant_due_date');
            });
        }

        if (Schema::hasTable('chat_campaigns')) {
            Schema::table('chat_campaigns', function (Blueprint $table): void {
                $table->dropIndex('idx_campaigns_tenant_status');
                $table->dropIndex('idx_campaigns_tenant_scheduled');
            });
        }

        if (Schema::hasTable('ai_usage_logs')) {
            Schema::table('ai_usage_logs', function (Blueprint $table): void {
                $table->dropIndex('idx_usage_logs_tenant_created');
            });
        }
    }
};
