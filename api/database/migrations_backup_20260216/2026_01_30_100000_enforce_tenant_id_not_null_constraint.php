<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce NOT NULL constraint on tenant_id columns.
 *
 * This migration ensures tenant isolation is mandatory for all
 * tenant-scoped tables. Webhook tables are excluded as they may
 * receive events before tenant identification.
 *
 * IMPORTANT: Run this only after ensuring no NULL tenant_id values exist.
 */
return new class extends Migration
{
    /**
     * Tables that should have tenant_id as NOT NULL.
     *
     * @var array<string>
     */
    private array $tables = [
        // AI Domain
        'ai_autopilot_guardrails',
        'ai_autopilot_playbooks',
        'ai_autopilot_runs',
        'ai_autopilot_tools',
        'ai_knowledge_chunks',
        'ai_knowledge_documents',
        'ai_post_sale_schedules',
        'ai_prompt_tenants',
        'ai_seller_notifications',
        'ai_usage_logs',

        // Billing Domain
        'billing_invoices',
        'billing_payments',

        // CRM Domain
        'crm_companies',
        'crm_contacts',
        'crm_contact_phones',
        'crm_negotiations',
        'crm_negotiation_files',
        'crm_negotiation_funnels',
        'crm_negotiation_funnel_steps',
        'crm_negotiation_tasks',
        'crm_products',
        'crm_proposals',
        'crm_proposal_items',
        'crm_reason_losses',
        'crm_tags',
        'crm_custom_fields',
        'crm_custom_field_values',
        'crm_departments',
        'crm_events',
        'crm_event_links',
        'crm_event_participants',
        'crm_event_reminders',
        'crm_negotiation_products',
        'crm_notes',

        // Chat Domain
        'chat_campaigns',
        'chat_campaign_contacts',
        'chat_chatbot_cooldowns',
        'chat_chatbot_rules',
        'chat_instances',
        'chat_messages',
        'chat_message_templates',
        'chat_quick_answers',
        'chat_tickets',
        'chat_ticket_evaluations',
        'chat_ticket_sequences',
        'chat_ticket_transfers',

        // Configuration Domain
        'configuration_opening_hours',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                // First, check if there are any NULL values
                $nullCount = \DB::table($table)->whereNull('tenant_id')->count();

                if ($nullCount > 0) {
                    throw new \RuntimeException(
                        "Cannot apply NOT NULL constraint: {$table} has {$nullCount} rows with NULL tenant_id. ".
                        'Please clean up the data first.'
                    );
                }

                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->uuid('tenant_id')->nullable(false)->change();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->uuid('tenant_id')->nullable()->change();
                });
            }
        }
    }
};
