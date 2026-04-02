<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Extra plans seeder — intentionally empty.
 *
 * All plan tiers (Starter, Professional, Business) are now defined
 * in PlatformPlanSeeder. This seeder is kept as a no-op for backward
 * compatibility with any code that calls it explicitly.
 */
class PlatformPlanExtraSeeder extends Seeder
{
    public function run(): void
    {
        // All plan tiers (Starter, Professional, Business) are defined
        // in PlatformPlanSeeder. This seeder is kept as a no-op for backward
        // compatibility with any code that calls it explicitly.
        $this->command->info('PlatformPlanExtraSeeder: no extra plans (all tiers live in PlatformPlanSeeder)');
    }
}
