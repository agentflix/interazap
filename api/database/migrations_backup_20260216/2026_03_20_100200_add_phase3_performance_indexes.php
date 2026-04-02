<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add Phase 3 performance indexes from API audit remediation.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Chat Tickets indexes (MEDIUM priority)
        if (Schema::hasTable('chat_tickets')) {
            Schema::table('chat_tickets', function (Blueprint $table): void {
                $table->index(['tenant_id', 'assigned_to'], 'idx_tickets_tenant_assigned');
                $table->index(['tenant_id', 'contact_id'], 'idx_tickets_tenant_contact');
                $table->index(['tenant_id', 'last_message_at'], 'idx_tickets_tenant_last_msg');
            });
        }

        // CRM Negotiations indexes (HIGH priority)
        if (Schema::hasTable('crm_negotiations')) {
            Schema::table('crm_negotiations', function (Blueprint $table): void {
                $table->index(['tenant_id', 'crm_negotiation_funnel_step_id'], 'idx_negotiations_tenant_step');
                $table->index(['tenant_id', 'crm_contact_id'], 'idx_negotiations_tenant_contact');
                $table->index(['tenant_id', 'status', 'position'], 'idx_negotiations_tenant_status_position');
            });
        }

        // CRM Custom Field Values indexes (HIGH priority)
        if (Schema::hasTable('crm_custom_field_values')) {
            Schema::table('crm_custom_field_values', function (Blueprint $table): void {
                $table->index(['entity_type', 'entity_id'], 'idx_custom_field_values_entity');
                $table->index(['crm_custom_field_id'], 'idx_custom_field_values_field');
            });
        }

        // CRM Contacts indexes (MEDIUM priority)
        if (Schema::hasTable('crm_contacts')) {
            Schema::table('crm_contacts', function (Blueprint $table): void {
                $table->index(['tenant_id', 'is_active'], 'idx_contacts_tenant_active');
                $table->index(['tenant_id', 'phone'], 'idx_contacts_tenant_phone');
                $table->index(['tenant_id', 'whatsapp'], 'idx_contacts_tenant_whatsapp');
            });

            // Full-text search indexes
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_contacts_name_trgm ON crm_contacts USING GIN (name gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_crm_contacts_email_trgm ON crm_contacts USING GIN (email gin_trgm_ops)');
        }

        // AI indexes
        if (Schema::hasTable('ai_usage_logs')) {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_ai_usage_logs_created_date ON ai_usage_logs (DATE(created_at))');
        }

        if (Schema::hasTable('ai_seller_notifications')) {
            Schema::table('ai_seller_notifications', function (Blueprint $table): void {
                $table->index(['tenant_id', 'seller_id', 'delivered_at'], 'idx_ai_seller_notifications_tenant_seller_delivered');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Chat Tickets indexes
        if (Schema::hasTable('chat_tickets')) {
            Schema::table('chat_tickets', function (Blueprint $table): void {
                $table->dropIndex('idx_tickets_tenant_assigned');
                $table->dropIndex('idx_tickets_tenant_contact');
                $table->dropIndex('idx_tickets_tenant_last_msg');
            });
        }

        // CRM Negotiations indexes
        if (Schema::hasTable('crm_negotiations')) {
            Schema::table('crm_negotiations', function (Blueprint $table): void {
                $table->dropIndex('idx_negotiations_tenant_step');
                $table->dropIndex('idx_negotiations_tenant_contact');
                $table->dropIndex('idx_negotiations_tenant_status_position');
            });
        }

        // CRM Custom Field Values indexes
        if (Schema::hasTable('crm_custom_field_values')) {
            Schema::table('crm_custom_field_values', function (Blueprint $table): void {
                $table->dropIndex('idx_custom_field_values_entity');
                $table->dropIndex('idx_custom_field_values_field');
            });
        }

        // CRM Contacts indexes
        if (Schema::hasTable('crm_contacts')) {
            Schema::table('crm_contacts', function (Blueprint $table): void {
                $table->dropIndex('idx_contacts_tenant_active');
                $table->dropIndex('idx_contacts_tenant_phone');
                $table->dropIndex('idx_contacts_tenant_whatsapp');
            });

            DB::statement('DROP INDEX IF EXISTS idx_crm_contacts_name_trgm');
            DB::statement('DROP INDEX IF EXISTS idx_crm_contacts_email_trgm');
        }

        // AI indexes
        if (Schema::hasTable('ai_usage_logs')) {
            DB::statement('DROP INDEX IF EXISTS idx_ai_usage_logs_created_date');
        }

        if (Schema::hasTable('ai_seller_notifications')) {
            Schema::table('ai_seller_notifications', function (Blueprint $table): void {
                $table->dropIndex('idx_ai_seller_notifications_tenant_seller_delivered');
            });
        }
    }
};
