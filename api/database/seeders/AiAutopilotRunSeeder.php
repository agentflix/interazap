<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Models\AiAutopilotAction;
use Domain\Ai\Models\AiAutopilotApproval;
use Domain\Ai\Models\AiAutopilotPlaybook;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed autopilot runs with actions and approvals per tenant.
 */
class AiAutopilotRunSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping AiAutopilotRunSeeder.');

            return;
        }

        $totalRuns = 0;
        $totalActions = 0;
        $totalApprovals = 0;

        foreach ($tenants as $tenant) {
            $playbooks = AiAutopilotPlaybook::query()->where('tenant_id', $tenant->id)->get();
            if ($playbooks->isEmpty()) {
                continue;
            }

            AiAutopilotRun::query()
                ->where('tenant_id', $tenant->id)
                ->whereRaw("input_context->>'seed_source' = ?", ['ai_module_seeder'])
                ->delete();

            $users = AuthUser::query()->where('tenant_id', $tenant->id)->where('is_active', true)->get();

            $runsToCreate = random_int(8, 12);
            $statuses = $this->buildStatusDistribution($runsToCreate);

            foreach ($statuses as $index => $status) {
                $playbook = $playbooks->random();
                $startedAt = \Illuminate\Support\Facades\Date::now()->subDays(random_int(1, 30))->subMinutes(random_int(0, 600));
                $completedAt = in_array($status, ['completed', 'failed', 'cancelled'], true)
                    ? (clone $startedAt)->addMinutes(random_int(2, 120))
                    : null;

                $run = AiAutopilotRun::query()->create([
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $tenant->id,
                    'playbook_id' => $playbook->id,
                    'status' => $status,
                    'playbook_version' => (int) $playbook->version,
                    'input_context' => [
                        'seed_source' => 'ai_module_seeder',
                        'seed_key' => sprintf('%s-run-%d', $tenant->id, $index + 1),
                        'channel' => 'whatsapp',
                    ],
                    'output' => $status === 'failed'
                        ? ['error' => 'Tool timeout while fetching customer data.']
                        : ['summary' => 'Run executed successfully.'],
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                ]);

                $totalRuns++;

                $actions = $this->actionsCountByStatus($status);
                for ($order = 1; $order <= $actions; $order++) {
                    AiAutopilotAction::query()->create([
                        'id' => (string) Str::orderedUuid(),
                        'tenant_id' => $tenant->id,
                        'run_id' => $run->id,
                        'action_type' => fake()->randomElement(['tool_call', 'decision', 'message_send']),
                        'input' => [
                            'seed_source' => 'ai_module_seeder',
                            'order' => $order,
                            'payload' => 'action input payload',
                        ],
                        'output' => [
                            'status' => 'ok',
                            'message' => 'Action completed',
                        ],
                        'guardrail_result' => fake()->randomElement(['allowed', 'warned', 'blocked']),
                        'order' => $order,
                    ]);

                    $totalActions++;
                }

                if (random_int(1, 100) <= 20) {
                    $approvalStatus = fake()->randomElement(['pending', 'approved', 'rejected']);
                    $approver = $approvalStatus === 'approved' && $users->isNotEmpty() ? $users->random() : null;

                    AiAutopilotApproval::query()->create([
                        'id' => (string) Str::orderedUuid(),
                        'tenant_id' => $tenant->id,
                        'run_id' => $run->id,
                        'status' => $approvalStatus,
                        'requested_action' => [
                            'seed_source' => 'ai_module_seeder',
                            'type' => 'manual_approval',
                        ],
                        'approved_by' => $approver?->id,
                        'approved_at' => $approvalStatus === 'approved' ? now() : null,
                        'rejected_at' => $approvalStatus === 'rejected' ? now() : null,
                        'rejected_reason' => $approvalStatus === 'rejected' ? 'Needs supervisor validation.' : null,
                    ]);

                    $totalApprovals++;
                }
            }
        }

        $this->command->info(sprintf('AI Autopilot Runs seeded: %d runs, %d actions, %d approvals', $totalRuns, $totalActions, $totalApprovals));
    }

    /**
     * @return array<int, string>
     */
    private function buildStatusDistribution(int $total): array
    {
        $completed = (int) round($total * 0.40);
        $running = (int) round($total * 0.25);
        $failed = (int) round($total * 0.20);
        $cancelled = max(0, $total - ($completed + $running + $failed));

        $statuses = array_merge(
            array_fill(0, $completed, 'completed'),
            array_fill(0, $running, 'running'),
            array_fill(0, $failed, 'failed'),
            array_fill(0, $cancelled, 'cancelled')
        );

        shuffle($statuses);

        return $statuses;
    }

    private function actionsCountByStatus(string $status): int
    {
        return match ($status) {
            'completed' => random_int(3, 6),
            'running' => random_int(1, 3),
            'failed' => random_int(2, 4),
            'cancelled' => random_int(1, 2),
            default => 1,
        };
    }
}
