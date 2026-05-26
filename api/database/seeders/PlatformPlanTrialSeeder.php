<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Platform\Models\PlatformPlan;
use Illuminate\Database\Seeder;

/**
 * Cria o plano Trial com is_trial=true e cota de 7 dias / 100 mensagens IA.
 *
 * Idempotente — pode ser executado múltiplas vezes sem duplicar o registro.
 */
class PlatformPlanTrialSeeder extends Seeder
{
    public function run(): void
    {
        PlatformPlan::query()->updateOrCreate(
            ['slug' => 'trial'],
            [
                'name' => 'Trial',
                'slug' => 'trial',
                'price_monthly' => 0.00,
                'cycle_days' => 7,
                'is_trial' => true,
                'message_limit_monthly' => 100,
                'overage_mode' => 'stop',
                'overage_price_per_message' => null,
                'limit_users' => 5,
                'chat_channels_limit' => 1,
                'storage_mode' => \Domain\Platform\Enums\PlatformStorageMode::LIMITED->value,
                'storage_limit_bytes' => 1024 * 1024 * 500, // 500 MB
                'ai_enabled' => true,
                'message_retention_days' => 30,
                'negotiations_mode' => \Domain\Platform\Enums\PlatformNegotiationsMode::LIMITED->value,
                'negotiations_limit' => 20,
                'reports_mode' => \Domain\Platform\Enums\PlatformReportsMode::BASIC->value,
                'asaas_product_id' => null,
                'is_active' => true,
            ]
        );
    }
}
