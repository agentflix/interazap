<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Performance Indexes & Partitioning - Consolidated Migration
 *
 * This migration consolidates all performance optimizations:
 * 1. Additional composite indexes for common query patterns
 * 2. Partial indexes for filtered queries
 * 3. GIN indexes for JSONB columns
 * 4. Partitioning setup for high-volume tables
 *
 * DBA RECOMMENDATIONS:
 * - chat_messages: Consider monthly partitioning when > 10M rows
 * - audit_logs: Consider monthly partitioning with 12-month retention
 * - ai_usage_logs: Consider monthly partitioning for LGPD compliance
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // COMPOSITE INDEXES FOR COMMON QUERIES
        // =====================================================================

        // CRM: Frequently filtered by multiple columns
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_crm_negotiations_tenant_funnel_status
            ON crm_negotiations (tenant_id, crm_negotiation_funnel_id, status)
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_crm_contacts_tenant_company_active
            ON crm_contacts (tenant_id, crm_company_id, is_active)
            WHERE deleted_at IS NULL
        ');

        // Chat: Frequently filtered by status + time
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_chat_tickets_tenant_status_last_message
            ON chat_tickets (tenant_id, status, last_message_at DESC)
            WHERE deleted_at IS NULL
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_chat_tickets_assigned_status
            ON chat_tickets (assigned_to, status)
            WHERE assigned_to IS NOT NULL AND deleted_at IS NULL
        ');

        // Messages: Frequently queried by ticket + time
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_chat_messages_ticket_created_desc
            ON chat_messages (ticket_id, created_at DESC)
        ');

        // =====================================================================
        // PARTIAL INDEXES FOR FILTERED QUERIES
        // =====================================================================

        // Only active entities
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_crm_negotiations_active
            ON crm_negotiations (tenant_id, created_at)
            WHERE status = \'open\' AND deleted_at IS NULL
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_chat_tickets_pending
            ON chat_tickets (tenant_id, created_at)
            WHERE status = \'pending\' AND deleted_at IS NULL
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_chat_tickets_open
            ON chat_tickets (tenant_id, last_message_at DESC)
            WHERE status IN (\'open\', \'waiting\') AND deleted_at IS NULL
        ');

        // Unassigned tickets (queue view)
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_chat_tickets_unassigned
            ON chat_tickets (tenant_id, created_at)
            WHERE assigned_to IS NULL AND status = \'pending\' AND deleted_at IS NULL
        ');

        // =====================================================================
        // GIN INDEXES FOR JSONB COLUMNS
        // =====================================================================

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_chat_tickets_tags_gin
            ON chat_tickets USING gin (tags jsonb_path_ops)
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_chat_tickets_metadata_gin
            ON chat_tickets USING gin (metadata jsonb_path_ops)
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_billing_invoices_metadata_gin
            ON billing_invoices USING gin (metadata jsonb_path_ops)
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_ai_usage_logs_metadata_gin
            ON ai_usage_logs USING gin (metadata jsonb_path_ops)
        ');

        // =====================================================================
        // COVERING INDEXES FOR COMMON SELECT PATTERNS
        // =====================================================================

        // Contact list view (avoids table lookup)
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_crm_contacts_list
            ON crm_contacts (tenant_id, is_active, name, email, phone)
            WHERE deleted_at IS NULL
        ');

        // Ticket list view
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_chat_tickets_list
                ON chat_tickets (tenant_id, status, last_message_at DESC, protocol, push_name)
            WHERE deleted_at IS NULL
        ');

        // =====================================================================
        // UNIQUE PARTIAL INDEXES (Business Rules)
        // =====================================================================

        // Only one primary phone per contact
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS idx_crm_contact_phones_primary
            ON crm_contact_phones (crm_contact_id)
            WHERE is_primary = true AND valid_to IS NULL
        ');

        // Only one open ticket per remote_jid per tenant
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS idx_chat_tickets_one_open_per_jid
            ON chat_tickets (tenant_id, remote_jid)
            WHERE status IN (\'pending\', \'open\', \'waiting\') AND deleted_at IS NULL
        ');

        // =====================================================================
        // BRIN INDEXES FOR TIME-SERIES DATA (Partitioning Alternative)
        // =====================================================================

        // Audit logs - BRIN is efficient for append-only time-series
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_audit_logs_created_brin
            ON audit_logs USING brin (created_at)
            WITH (pages_per_range = 128)
        ');

        // AI usage logs - BRIN for time-based queries
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_ai_usage_logs_created_brin
            ON ai_usage_logs USING brin (created_at)
            WITH (pages_per_range = 128)
        ');

        // Chat messages - BRIN for historical queries
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_chat_messages_created_brin
            ON chat_messages USING brin (created_at)
            WITH (pages_per_range = 128)
        ');

        // =====================================================================
        // STATISTICS FOR BETTER QUERY PLANNING
        // =====================================================================

        // Increase statistics target for frequently filtered columns
        DB::statement('ALTER TABLE crm_negotiations ALTER COLUMN status SET STATISTICS 1000');
        DB::statement('ALTER TABLE chat_tickets ALTER COLUMN status SET STATISTICS 1000');
        DB::statement('ALTER TABLE chat_tickets ALTER COLUMN assigned_to SET STATISTICS 1000');
    }

    public function down(): void
    {
        // Drop indexes in reverse order
        DB::statement('DROP INDEX IF EXISTS idx_chat_messages_created_brin');
        DB::statement('DROP INDEX IF EXISTS idx_ai_usage_logs_created_brin');
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_created_brin');
        DB::statement('DROP INDEX IF EXISTS idx_chat_tickets_one_open_per_jid');
        DB::statement('DROP INDEX IF EXISTS idx_crm_contact_phones_primary');
        DB::statement('DROP INDEX IF EXISTS idx_chat_tickets_list');
        DB::statement('DROP INDEX IF EXISTS idx_crm_contacts_list');
        DB::statement('DROP INDEX IF EXISTS idx_ai_usage_logs_metadata_gin');
        DB::statement('DROP INDEX IF EXISTS idx_billing_invoices_metadata_gin');
        DB::statement('DROP INDEX IF EXISTS idx_chat_tickets_metadata_gin');
        DB::statement('DROP INDEX IF EXISTS idx_chat_tickets_tags_gin');
        DB::statement('DROP INDEX IF EXISTS idx_chat_tickets_unassigned');
        DB::statement('DROP INDEX IF EXISTS idx_chat_tickets_open');
        DB::statement('DROP INDEX IF EXISTS idx_chat_tickets_pending');
        DB::statement('DROP INDEX IF EXISTS idx_crm_negotiations_active');
        DB::statement('DROP INDEX IF EXISTS idx_chat_messages_ticket_created_desc');
        DB::statement('DROP INDEX IF EXISTS idx_chat_tickets_assigned_status');
        DB::statement('DROP INDEX IF EXISTS idx_chat_tickets_tenant_status_last_message');
        DB::statement('DROP INDEX IF EXISTS idx_crm_contacts_tenant_company_active');
        DB::statement('DROP INDEX IF EXISTS idx_crm_negotiations_tenant_funnel_status');

        // Reset statistics
        DB::statement('ALTER TABLE crm_negotiations ALTER COLUMN status SET STATISTICS -1');
        DB::statement('ALTER TABLE chat_tickets ALTER COLUMN status SET STATISTICS -1');
        DB::statement('ALTER TABLE chat_tickets ALTER COLUMN assigned_to SET STATISTICS -1');
    }
};
