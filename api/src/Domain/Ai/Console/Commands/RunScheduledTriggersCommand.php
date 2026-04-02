<?php

declare(strict_types=1);

namespace Domain\Ai\Console\Commands;

use Domain\Ai\Models\AiAgentTrigger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * Command to evaluate due AI agent cron triggers and publish run request events.
 */
final class RunScheduledTriggersCommand extends Command
{
    protected $signature = 'ai:run-scheduled-triggers';

    protected $description = 'Evaluate due agent cron triggers and publish ai.run.request stream events.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $triggers = AiAgentTrigger::query()
            ->where('status', 'active')
            ->where('type', 'cron')
            ->get();

        $published = 0;

        foreach ($triggers as $trigger) {
            $expression = (string) data_get($trigger->config, 'expression', '* * * * *');
            $cron = \Cron\CronExpression::factory($expression);

            if (! $cron->isDue(now()->toDateTimeString())) {
                continue;
            }

            $dedupeKey = sprintf('ai:scheduled-trigger:%s:%s', (string) $trigger->id, now()->format('YmdHi'));
            if (! Cache::add($dedupeKey, '1', now()->addMinutes(2))) {
                continue;
            }

            $streamPayload = [
                'event' => 'ai.run.request',
                'tenant_id' => (string) $trigger->tenant_id,
                'agent_id' => (string) $trigger->agent_id,
                'trigger_id' => (string) $trigger->id,
                'source' => 'scheduled_trigger',
                'requested_at' => now()->toIso8601String(),
            ];

            /** @var \Illuminate\Redis\Connections\Connection $redis */
            $redis = Redis::connection(config('gateway.redis.connection', 'gateway'));
            $redis->xadd('ai.run.request', '*', $streamPayload);

            $trigger->last_run_at = now();
            $trigger->save();

            $published++;
        }

        $this->info(sprintf('Published %d scheduled ai.run.request events.', $published));

        return self::SUCCESS;
    }
}
