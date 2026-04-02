<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orchestrates all demo/sample data seeders.
 *
 * Should only run in local/staging environments.
 * Triggered automatically when SEED_DEMO_DATA=true, or manually via:
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ── Multi-tenant base (roles, plans, tenants, users) ──────────
            DemoDataSeeder::class,
            PlatformPlanExtraSeeder::class,
            PlatformTenantSeeder::class,
            AuthExtraUsersSeeder::class,

            // ── CRM ───────────────────────────────────────────────────────
            CRMCompanySeeder::class,
            CRMContactSeeder::class,
            CRMCustomFieldSeeder::class,
            CRMCustomFieldValueSeeder::class,
            CRMProductSeeder::class,
            CRMReasonLossSeeder::class,
            CRMDepartmentSeeder::class,
            CRMTagSeeder::class,
            CRMNegotiationFunnelSeeder::class,
            CRMNegotiationSeeder::class,
            CRMProposalSeeder::class,
            CRMEventSeeder::class,

            // ── Configuration ─────────────────────────────────────────────
            ConfigurationOpeningHourSeeder::class,

            // ── Billing ───────────────────────────────────────────────────
            BillingInvoiceSeeder::class,

            // ── Chat ──────────────────────────────────────────────────────
            ChatQuickAnswerSeeder::class,
            ChatAutoReplySeeder::class,
            ChatCampaignSeeder::class,
            ChatConversationScenarioSeeder::class,

            // ── AI (per-tenant demo data) ─────────────────────────────────
            AiModuleSeeder::class,

            // Re-runs after demo plans (starter, growth, pro) are created
            // so that AiPromptPlan records cover all plans, not just system ones.
            AiPromptPlanSeeder::class,

            // ── Reports data ────────────────────────────────────────────────
            ReportsDataSeeder::class,
        ]);
    }
}
