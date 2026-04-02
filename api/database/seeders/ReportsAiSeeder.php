<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Models\AiUsageLog;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds AI data for report testing.
 *
 * Covers: AI Usage Cost, Autopilot Performance reports.
 */
final class ReportsAiSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('Nenhum tenant encontrado. Execute DatabaseSeeder primeiro.');

            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedUsageLogs($tenant->id);
            $this->seedAutopilotRuns($tenant->id);
        }
    }

    private function seedUsageLogs(string $tenantId): void
    {
        // Get users for this tenant
        $users = AuthUser::query()
            ->where('tenant_id', $tenantId)
            ->get();

        $features = ['chat', 'transcription', 'summarization', 'suggestion'];
        $models = ['claude-3-5-sonnet', 'gpt-4o', 'gemini-2.0-flash'];
        $providers = ['anthropic', 'openai', 'google'];

        // Create 100 usage log entries
        for ($i = 0; $i < min(100, 100); $i++) {
            $user = $users->isNotEmpty() ? $users->random() : null;
            $inputTokens = rand(100, 10000);
            $outputTokens = rand(100, 5000);
            $inputCost = ($inputTokens / 1_000_000) * 3.00;
            $outputCost = ($outputTokens / 1_000_000) * 15.00;

            AiUsageLog::factory()
                ->create([
                    'tenant_id' => $tenantId,
                    'user_id' => $user?->id,
                    'feature' => fn () => $features[array_rand($features)],
                    'model_name' => fn () => $models[array_rand($models)],
                    'provider' => fn () => $providers[array_rand($providers)],
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'input_cost' => $inputCost,
                    'output_cost' => $outputCost,
                    'latency_ms' => rand(100, 5000),
                    'created_at' => now()->subDays(rand(0, 30)),
                ]);
        }
    }

    private function seedAutopilotRuns(string $tenantId): void
    {
        $classifierResults = ['message_received', 'scheduled', 'keyword', 'webhook', 'ticket_created', 'ticket_closed'];
        $statuses = ['completed', 'failed', 'running'];

        // Create 50 autopilot runs
        for ($i = 0; $i < min(50, 100); $i++) {
            $status = $statuses[array_rand($statuses)];
            $startedAt = now()->subDays(rand(0, 30));
            $completedAt = $status === 'completed' ? $startedAt->copy()->addSeconds(rand(1, 300)) : null;

            AiAutopilotRun::factory()
                ->create([
                    'tenant_id' => $tenantId,
                    'classifier_result' => fn () => $classifierResults[array_rand($classifierResults)],
                    'status' => $status,
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'created_at' => $startedAt,
                ]);
        }
    }
}
