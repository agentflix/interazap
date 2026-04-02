<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orchestrates all report data seeders.
 *
 * These seeders populate data specifically for testing the reports module.
 * Run after DemoDataSeeder to ensure tenants and base data exist.
 *
 * Usage:
 *   php artisan db:seed --class=ReportsDataSeeder
 *
 * Or via DemoSeeder when SEED_DEMO_DATA=true.
 */
final class ReportsDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReportsChatSeeder::class,
            ReportsCrmSeeder::class,
            ReportsBillingSeeder::class,
            ReportsAiSeeder::class,
            ReportsContactsSeeder::class,
        ]);
    }
}
