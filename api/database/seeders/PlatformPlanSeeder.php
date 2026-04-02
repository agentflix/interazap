<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformReportsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Database\Seeder;

/**
 * Seeder for Platform Plans (3 tiers: Starter, Professional, Business).
 */
class PlatformPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'limit_users' => 5,
                'whatsapp_integrations_limit' => 1,
                'storage_mode' => PlatformStorageMode::LIMITED->value,
                'storage_limit_bytes' => 1 * 1024 * 1024 * 1024,
                'ai_enabled' => false,
                'negotiations_mode' => PlatformNegotiationsMode::LIMITED->value,
                'negotiations_limit' => 50,
                'reports_mode' => PlatformReportsMode::BASIC->value,
                'price_monthly' => 97.00,
                'is_active' => true,
            ],
            [
                'slug' => 'professional',
                'name' => 'Professional',
                'limit_users' => 20,
                'whatsapp_integrations_limit' => 5,
                'storage_mode' => PlatformStorageMode::LIMITED->value,
                'storage_limit_bytes' => 5 * 1024 * 1024 * 1024,
                'ai_enabled' => true,
                'negotiations_mode' => PlatformNegotiationsMode::LIMITED->value,
                'negotiations_limit' => 500,
                'reports_mode' => PlatformReportsMode::ADVANCED->value,
                'price_monthly' => 297.00,
                'is_active' => true,
            ],
            [
                'slug' => 'business',
                'name' => 'Business',
                'limit_users' => 100,
                'whatsapp_integrations_limit' => 25,
                'storage_mode' => PlatformStorageMode::UNLIMITED->value,
                'storage_limit_bytes' => null,
                'ai_enabled' => true,
                'negotiations_mode' => PlatformNegotiationsMode::UNLIMITED->value,
                'negotiations_limit' => null,
                'reports_mode' => PlatformReportsMode::FULL->value,
                'price_monthly' => 897.00,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $planData) {
            $plan = PlatformPlan::query()->updateOrCreate(
                ['slug' => $planData['slug']],
                [
                    'name' => $planData['name'],
                    'slug' => $planData['slug'],
                    'limit_users' => $planData['limit_users'],
                    'storage_mode' => $planData['storage_mode'],
                    'storage_limit_bytes' => $planData['storage_limit_bytes'],
                    'ai_enabled' => $planData['ai_enabled'],
                    'whatsapp_integrations_limit' => $planData['whatsapp_integrations_limit'],
                    'negotiations_mode' => $planData['negotiations_mode'],
                    'negotiations_limit' => $planData['negotiations_limit'],
                    'reports_mode' => $planData['reports_mode'],
                    'price_monthly' => $planData['price_monthly'],
                    'asaas_product_id' => null,
                    'is_active' => $planData['is_active'],
                ]
            );

            $this->command->info("Plan '{$plan->name}' (slug: {$plan->slug}) upserted — AI: {$plan->ai_enabled}, Reports: {$plan->reports_mode->value}");
        }
    }
}
