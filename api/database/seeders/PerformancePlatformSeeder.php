<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

/**
 * Performance seed for Platform context.
 *
 * Creates Uazapi instances and leads per tenant using raw inserts.
 */
final class PerformancePlatformSeeder
{
    use WithoutModelEvents;

    /** Batch size for inserts. */
    private const int BATCH_SIZE = 1000;

    public function seedForTenant(string $tenantId): void
    {
        $this->seedUazapiInstances($tenantId);
        $this->seedLeads();
    }

    /**
     * Create 2-3 Uazapi instances per tenant with varied statuses and providers.
     */
    private function seedUazapiInstances(string $tenantId): void
    {
        $weights = ['active' => 50, 'inactive' => 20, 'connecting' => 15, 'error' => 15];

        $instances = [];
        $count = random_int(2, 3);

        for ($i = 0; $i < $count; $i++) {
            $instances[] = [
                'id' => PerformanceSeeder::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Instance '.($i + 1).' — '.Str::random(4),
                'system_name' => 'instance_'.($i + 1).'_'.Str::slug(Str::random(4)),
                'token' => (string) Str::uuid(),
                'status' => PerformanceSeeder::weightedRandom($weights),
                'webhook_url' => 'https://webhook.perf.local/'.Str::random(8),
                'config' => json_encode(['debug' => (bool) random_int(0, 1)]),
                'metadata' => json_encode(['version' => '1.0.'.random_int(0, 9)]),
                'last_status_at' => PerformanceSeeder::randomDate(),
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('platform_uazapi_instances', $instances, self::BATCH_SIZE);
    }

    /**
     * Create ~10 leads per tenant.
     */
    private function seedLeads(): void
    {
        $faker = fake('pt_BR');
        $sources = ['landing_page', 'facebook_ads', 'google_ads', 'organic', 'referral', 'event', 'whatsapp'];

        $leads = [];
        $count = random_int(8, 12);

        for ($i = 0; $i < $count; $i++) {
            $hasUtm = (bool) random_int(0, 1);
            $hasConsent = (bool) random_int(0, 1);

            $leads[] = [
                'id' => PerformanceSeeder::uuid(),
                'name' => $faker->name(),
                'phone' => '+55'.random_int(1100000000, 99999999999),
                'email' => 'lead.'.random_int(1000, 9999).'@example.com',
                'company' => $faker->company(),
                'utm_source' => $hasUtm ? $sources[array_rand($sources)] : null,
                'utm_medium' => $hasUtm ? ['cpc', 'organic', 'social', 'email'][array_rand(['cpc', 'organic', 'social', 'email'])] : null,
                'utm_campaign' => $hasUtm ? 'campaign_'.random_int(1, 20) : null,
                'referrer' => $hasUtm ? 'https://example.com/page'.random_int(1, 10) : null,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'ip_address' => $faker->ipv4(),
                'lgpd_consent' => $hasConsent,
                'created_at' => PerformanceSeeder::randomDate(),
                'updated_at' => now(),
            ];
        }

        PerformanceSeeder::insertBatch('platform_leads', $leads, self::BATCH_SIZE);
    }
}
