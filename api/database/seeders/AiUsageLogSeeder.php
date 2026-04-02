<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Models\AiModelPricing;
use Domain\Ai\Models\AiUsageLog;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Seed realistic AI usage logs for the last 30 days.
 */
class AiUsageLogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping AiUsageLogSeeder.');

            return;
        }

        $pricingByModel = AiModelPricing::query()
            ->where('is_active', true)
            ->get()
            ->keyBy('model_name');

        if ($pricingByModel->isEmpty()) {
            $this->command->warn('No AI model pricing records found. Skipping AiUsageLogSeeder.');

            return;
        }

        $total = 0;

        foreach ($tenants as $tenant) {
            AiUsageLog::query()
                ->where('tenant_id', $tenant->id)
                ->whereRaw("metadata->>'seed_source' = ?", ['ai_module_seeder'])
                ->delete();

            $users = AuthUser::query()->where('tenant_id', $tenant->id)->where('is_active', true)->get();
            $records = min(random_int(50, 100), 100);
            $featurePool = $this->buildFeaturePool($records);
            $rows = [];

            for ($index = 0; $index < $records; $index++) {
                $feature = $featurePool[$index] ?? 'chat_response';
                $modelName = $this->resolveModelForFeature($feature);
                $pricing = $pricingByModel->get($modelName);

                if (! $pricing instanceof AiModelPricing) {
                    continue;
                }

                $inputTokens = random_int(120, 4200);
                $outputTokens = random_int(60, 1800);
                $inputCost = round(($inputTokens / 1_000_000) * (float) $pricing->input_cost_per_1m, 6);
                $outputCost = round(($outputTokens / 1_000_000) * (float) $pricing->output_cost_per_1m, 6);

                $timestamp = $this->randomBusinessBiasedTimestamp();

                $rows[] = [
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $tenant->id,
                    'user_id' => $users->isNotEmpty() ? $users->random()->id : null,
                    'ai_model_pricing_id' => $pricing->id,
                    'model_name' => $pricing->model_name,
                    'provider' => $pricing->provider,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'input_cost' => $inputCost,
                    'output_cost' => $outputCost,
                    'request_id' => (string) Str::orderedUuid(),
                    'feature' => $feature,
                    'latency_ms' => random_int(150, 4500),
                    'usable_type' => null,
                    'usable_id' => null,
                    'metadata' => json_encode(['seed_source' => 'ai_module_seeder']),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            if ($rows !== []) {
                AiUsageLog::query()->insert($rows);
                $total += count($rows);
            }
        }

        $this->command->info(sprintf('AI Usage Logs seeded: %d', $total));
    }

    /**
     * @return array<int, string>
     */
    private function buildFeaturePool(int $records): array
    {
        $pool = array_merge(
            array_fill(0, (int) round($records * 0.45), 'chat_response'),
            array_fill(0, (int) round($records * 0.20), 'lead_qualification'),
            array_fill(0, (int) round($records * 0.15), 'knowledge_search'),
            array_fill(0, (int) round($records * 0.10), 'post_sale')
        );

        $remaining = max(0, $records - count($pool));
        $pool = array_merge($pool, array_fill(0, $remaining, 'summarization'));

        shuffle($pool);

        return $pool;
    }

    private function resolveModelForFeature(string $feature): string
    {
        return match ($feature) {
            'chat_response' => fake()->randomElement(['gpt-4o-mini', 'gpt-4o']),
            'lead_qualification' => 'gpt-4o',
            'knowledge_search' => 'text-embedding-3-small',
            'post_sale', 'summarization' => 'gpt-4o-mini',
            default => 'gpt-4o-mini',
        };
    }

    private function randomBusinessBiasedTimestamp(): Carbon
    {
        $base = \Illuminate\Support\Facades\Date::now()->subDays(random_int(0, 29));
        $hour = random_int(1, 100) <= 70 ? random_int(9, 18) : random_int(0, 23);

        return $base->setTime($hour, random_int(0, 59), random_int(0, 59));
    }
}
