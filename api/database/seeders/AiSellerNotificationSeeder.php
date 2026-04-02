<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Enums\AiNotificationReason;
use Domain\Ai\Models\AiSellerNotification;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed seller notifications for AI alerts.
 */
class AiSellerNotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping AiSellerNotificationSeeder.');

            return;
        }

        $created = 0;

        foreach ($tenants as $tenant) {
            $sellers = AuthUser::query()->where('tenant_id', $tenant->id)->where('is_active', true)->get();
            if ($sellers->isEmpty()) {
                continue;
            }

            AiSellerNotification::query()
                ->where('tenant_id', $tenant->id)
                ->whereRaw("metadata->>'seed_source' = ?", ['ai_module_seeder'])
                ->delete();

            $amount = min(random_int(10, 15), 100);
            for ($index = 0; $index < $amount; $index++) {
                $isEmail = random_int(1, 100) <= 70;
                $statusRand = random_int(1, 100);

                $deliveredAt = null;
                $failedAt = null;
                $errorMessage = null;

                if ($statusRand <= 80) {
                    $deliveredAt = now()->subMinutes(random_int(10, 600));
                } elseif ($statusRand <= 90) {
                    // keep pending
                } else {
                    $failedAt = now()->subMinutes(random_int(5, 120));
                    $errorMessage = 'Delivery provider timeout.';
                }

                AiSellerNotification::query()->create([
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $tenant->id,
                    'seller_id' => $sellers->random()->id,
                    'notifiable_type' => null,
                    'notifiable_id' => null,
                    'message' => fake()->sentence(10),
                    'reason' => fake()->randomElement(AiNotificationReason::cases())->value,
                    'channel' => $isEmail ? 'email' : 'whatsapp',
                    'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
                    'attempts' => random_int(0, 3),
                    'scheduled_at' => now()->subMinutes(random_int(30, 1000)),
                    'delivered_at' => $deliveredAt,
                    'failed_at' => $failedAt,
                    'error_message' => $errorMessage,
                    'metadata' => ['seed_source' => 'ai_module_seeder'],
                ]);

                $created++;
            }
        }

        $this->command->info(sprintf('AI Seller Notifications seeded: %d', $created));
    }
}
