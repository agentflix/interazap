<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo seeder for the AI module — per-tenant data only.
 *
 * Global system seeders (AiModelPricingSeeder, AiPromptMasterSeeder,
 * AiPromptSegmentSeeder, AiPromptPlanSeeder) are called by DatabaseSeeder.
 */
class AiModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            // Per-tenant demo data
            AiAutopilotToolSeeder::class,
            AiAutopilotPlaybookSeeder::class,
            AiAutopilotGuardrailSeeder::class,
            AiAutopilotRunSeeder::class,
            AiKnowledgeDocumentSeeder::class,
            AiPromptTenantSeeder::class,
            AiUsageLogSeeder::class,
            AiSellerNotificationSeeder::class,
            AiPostSaleScheduleSeeder::class,
        ]);
    }
}
