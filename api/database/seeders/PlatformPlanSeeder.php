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
                'chat_channels_limit' => 1,
                'storage_mode' => PlatformStorageMode::LIMITED->value,
                'storage_limit_bytes' => 1024 * 1024 * 1024,
                'ai_enabled' => false,
                'message_limit_monthly' => 90,
                'message_retention_days' => 90,
                'overage_mode' => 'stop',
                'overage_price_per_message' => null,
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
                'chat_channels_limit' => 5,
                'storage_mode' => PlatformStorageMode::LIMITED->value,
                'storage_limit_bytes' => 5 * 1024 * 1024 * 1024,
                'ai_enabled' => true,
                'message_limit_monthly' => 180,
                'message_retention_days' => 180,
                'overage_mode' => 'overage',
                'overage_price_per_message' => 0.02,
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
                'chat_channels_limit' => 25,
                'storage_mode' => PlatformStorageMode::UNLIMITED->value,
                'storage_limit_bytes' => null,
                'ai_enabled' => true,
                'message_limit_monthly' => 730,
                'message_retention_days' => 730,
                'overage_mode' => 'overage',
                'overage_price_per_message' => 0.015,
                'negotiations_mode' => PlatformNegotiationsMode::UNLIMITED->value,
                'negotiations_limit' => null,
                'reports_mode' => PlatformReportsMode::FULL->value,
                'price_monthly' => 897.00,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $planData) {
            PlatformPlan::query()->updateOrCreate(
                ['slug' => $planData['slug']],
                [
                    'name' => $planData['name'],
                    'slug' => $planData['slug'],
                    'limit_users' => $planData['limit_users'],
                    'storage_mode' => $planData['storage_mode'],
                    'storage_limit_bytes' => $planData['storage_limit_bytes'],
                    'ai_enabled' => $planData['ai_enabled'],
                    'message_limit_monthly' => $planData['message_limit_monthly'],
                    'message_retention_days' => $planData['message_retention_days'],
                    'overage_mode' => $planData['overage_mode'],
                    'overage_price_per_message' => $planData['overage_price_per_message'],
                    'chat_channels_limit' => $planData['chat_channels_limit'],
                    'negotiations_mode' => $planData['negotiations_mode'],
                    'negotiations_limit' => $planData['negotiations_limit'],
                    'reports_mode' => $planData['reports_mode'],
                    'price_monthly' => $planData['price_monthly'],
                    'asaas_product_id' => null,
                    'is_active' => $planData['is_active'],
                ]
            );
        }
    }
}
