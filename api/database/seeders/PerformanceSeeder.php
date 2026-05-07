<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Performance test data seeder.
 *
 * Generates ~150k records across 50 tenants with realistic variations
 * of statuses, dates, boolean flags, and tenant profiles.
 *
 * Anti-timeout strategy:
 * - Raw DB::table()->insert() in batches (ZERO factories/models)
 * - One tenant at a time with gc_collect_cycles()
 * - WithoutModelEvents trait
 * - DB::disableQueryLog()
 * - Transaction per tenant (not global)
 *
 * Usage:
 *   php artisan db:seed --class=PerformanceSeeder
 *   # or with env:
 *   PERFORMANCE_SEED=true php artisan db:seed
 */
final class PerformanceSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Number of tenants to create. */
    private const int TENANT_COUNT = 50;

    /** Batch size for raw inserts. */
    private const int BATCH_SIZE = 1000;

    /**
     * Tenant profiles distribution:
     * - 40 active (80%)
     * - 5 inactive (10%)
     * - 3 soft-deleted (6%)
     * - 2 locked/grace (4%)
     */
    private const array TENANT_PROFILES = [
        'active', 'active', 'active', 'active', 'active',
        'active', 'active', 'active', 'active', 'active',
        'active', 'active', 'active', 'active', 'active',
        'active', 'active', 'active', 'active', 'active',
        'active', 'active', 'active', 'active', 'active',
        'active', 'active', 'active', 'active', 'active',
        'active', 'active', 'active', 'active', 'active',
        'active', 'active', 'active', 'active', 'active',
        'inactive', 'inactive', 'inactive', 'inactive', 'inactive',
        'deleted', 'deleted', 'deleted',
        'locked', 'locked',
    ];

    /** @var array<int, string> Tenant IDs in creation order. */
    private array $tenantIds = [];

    public function run(): void
    {
        DB::disableQueryLog();
        $this->command->info('Starting performance seed...');
        $startTime = microtime(true);

        // 1. Create tenants
        $this->seedTenants();
        $this->command->info(sprintf('Created %d tenants', count($this->tenantIds)));

        // 2. Seed per tenant (chunked to avoid memory issues)
        $progress = $this->command->getOutput()->createProgressBar(count($this->tenantIds));
        $progress->start();

        foreach ($this->tenantIds as $index => $tenantId) {
            $this->seedTenant($tenantId, $index);
            $progress->advance();

            // Free memory every tenant
            gc_collect_cycles();
        }

        $progress->finish();
        $this->command->newLine();

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->command->info(sprintf('Performance seed completed in %.2f seconds.', $elapsed));
    }

    /**
     * Create 50 tenants with varied profiles.
     */
    private function seedTenants(): void
    {
        // Clean up existing performance tenants (re-runnable)
        $existingPerfTenants = DB::table('platform_tenants')
            ->where('tenant_code', 'like', 'PERF%')
            ->pluck('id')
            ->toArray();

        if (! empty($existingPerfTenants)) {
            $this->command->info(sprintf('Cleaning up %d existing performance tenants...', count($existingPerfTenants)));
            // Disable FK checks temporarily to allow cascade delete
            DB::statement("SET session_replication_role = 'replica';");
            DB::table('platform_tenants')->whereIn('id', $existingPerfTenants)->delete();
            DB::statement("SET session_replication_role = 'origin';");
        }

        $faker = fake('pt_BR');
        $plans = DB::table('platform_plans')->where('is_active', true)->pluck('id')->toArray();
        $defaultPlanId = $plans[0] ?? null;

        $tenants = [];
        foreach (self::TENANT_PROFILES as $index => $profile) {
            $tenantId = (string) Str::orderedUuid();
            $this->tenantIds[] = $tenantId;

            $companyName = $faker->company();
            $tenantCode = 'PERF'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            $tenant = [
                'id' => $tenantId,
                'name' => $companyName,
                'tenant_code' => $tenantCode,
                'primary_email' => strtolower(Str::slug($companyName)).'@perf.local',
                'document' => sprintf('%014d', random_int(10000000000, 99999999999)),
                'billing_webhook_token' => (string) Str::uuid(),
                'phone' => '+55'.random_int(1100000000, 99999999999),
                'street' => $faker->streetName(),
                'number' => (string) random_int(1, 9999),
                'complement' => random_int(0, 1) ? 'Sala '.random_int(100, 999) : null,
                'district' => $faker->citySuffix(),
                'city' => $faker->city(),
                'state' => $faker->stateAbbr(),
                'zip_code' => $faker->postcode(),
                'is_active' => $profile !== 'inactive' && $profile !== 'deleted',
                'plan_id' => $defaultPlanId,
                'billing_status' => match ($profile) {
                    'locked' => 'locked',
                    'deleted' => 'purge',
                    default => 'active',
                },
                'billing_locked_at' => $profile === 'locked' ? now()->subDays(random_int(1, 30)) : null,
                'billing_lock_reason' => $profile === 'locked' ? 'Inadimplencia' : null,
                'billing_grace_deadline' => $profile === 'locked' ? now()->addDays(15) : null,
                'billing_purge_deadline' => $profile === 'deleted' ? now()->subDays(5) : null,
                'media_transcription_audio_enabled' => (bool) random_int(0, 1),
                'media_transcription_image_enabled' => (bool) random_int(0, 1),
                'media_transcription_video_enabled' => (bool) random_int(0, 1),
                'settings_localization' => json_encode(['timezone' => 'America/Sao_Paulo', 'locale' => 'pt_BR']),
                'settings_privacy' => json_encode(['lgpd_enabled' => true]),
                'settings_chat' => json_encode(['auto_close' => true]),
                'created_at' => $this->randomDate(),
                'updated_at' => now(),
                'deleted_at' => $profile === 'deleted' ? now()->subDays(random_int(1, 30)) : null,
            ];

            $tenants[] = $tenant;
        }

        // Insert in batches
        foreach (array_chunk($tenants, self::BATCH_SIZE) as $chunk) {
            DB::table('platform_tenants')->insert($chunk);
        }
    }

    /**
     * Seed all contexts for a single tenant.
     */
    private function seedTenant(string $tenantId, int $tenantIndex): void
    {
        $profile = self::TENANT_PROFILES[$tenantIndex];

        // Skip data seeding for deleted tenants (only base tenant exists)
        if ($profile === 'deleted') {
            return;
        }

        DB::transaction(function () use ($tenantId): void {
            (new PerformancePlatformSeeder)->seedForTenant($tenantId);
            (new PerformanceAuthSeeder)->seedForTenant($tenantId);
            (new PerformanceCrmSeeder)->seedForTenant($tenantId);
            (new PerformanceChatSeeder)->seedForTenant($tenantId);
            (new PerformanceConfigurationSeeder)->seedForTenant($tenantId);
            (new PerformanceBillingSeeder)->seedForTenant($tenantId);
            (new PerformanceAiSeeder)->seedForTenant($tenantId);
            (new PerformanceSharedSeeder)->seedForTenant($tenantId);
        });
    }

    /**
     * Generate a random date within the last 180 days with temporal distribution.
     */
    public static function randomDate(): Carbon
    {
        $distributions = [
            30 => 7,    // 30% within last 7 days
            25 => 30,   // 25% within 7-30 days
            25 => 90,   // 25% within 30-90 days
            15 => 180,  // 15% within 90-180 days
            5 => 365,   // 5% within 180-365 days
        ];

        $rand = random_int(1, 100);
        $cumulative = 0;
        $days = 7;

        foreach ($distributions as $pct => $maxDays) {
            $cumulative += $pct;
            if ($rand <= $cumulative) {
                $days = $maxDays;
                break;
            }
        }

        return now()->subDays(random_int(1, $days));
    }

    /**
     * Generate a random status based on weights.
     *
     * @param  array<string, int>  $weights  Status => weight percentage
     */
    public static function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $rand = random_int(1, $total);
        $cumulative = 0;

        foreach ($weights as $status => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $status;
            }
        }

        return array_key_first($weights);
    }

    /**
     * Insert a batch of records using raw DB insert.
     *
     * @param  list<array<string, mixed>>  $records
     */
    public static function insertBatch(string $table, array $records, int $batchSize = 1000): void
    {
        foreach (array_chunk($records, $batchSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * Generate an ordered UUID.
     */
    public static function uuid(): string
    {
        return (string) Str::orderedUuid();
    }
}
