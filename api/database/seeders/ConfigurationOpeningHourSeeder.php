<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Configuration\Models\ConfigurationOpeningHour;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;

class ConfigurationOpeningHourSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        foreach ($tenants as $tenant) {
            for ($day = 1; $day <= 7; $day++) {
                $isWeekend = $day >= 6;

                ConfigurationOpeningHour::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'day_of_week' => $day],
                    [
                        'open_time' => $isWeekend ? '09:00:00' : '08:00:00',
                        'close_time' => $isWeekend ? '13:00:00' : '18:00:00',
                        'is_active' => ! $isWeekend,
                    ]
                );
            }
        }

        $this->command->info('Opening hours created.');
    }
}
