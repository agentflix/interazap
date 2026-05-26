<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Domain\Ai\Actions\AiAutopilotRunActions;
use Domain\Ai\Models\AiAutopilotRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Job de execução assíncrona de runs do Autopilot.
 *
 * Configura as mensagens iniciais e auto-despacha para runs com múltiplas iterações.
 * Cada iteração de tool call se auto-despacha para evitar o limite de
 * max_execution_time do PHP quando o LLM demora minutos para responder.
 */
final class AiRunExecutionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    /** @var array<int,int> */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public readonly string $runId,
        public readonly string $tenantId,
    ) {}

    /**
     * Executa uma iteração do run e auto-despacha ou finaliza conforme resultado.
     */
    public function handle(AiAutopilotRunActions $actions): void
    {
        // Prevent race condition: only one worker processes this run
        $lock = \Illuminate\Support\Facades\Cache::lock("ai:run:{$this->runId}", 60);

        if (! $lock->get()) {
            return; // Another worker is already processing
        }

        $run = AiAutopilotRun::query()->where('tenant_id', $this->tenantId)->findOrFail($this->runId);

        // Skip if already processed
        if (! in_array((string) $run->status, ['queued', 'running'], true)) {
            return;
        }

        $run->status = 'running';
        $run->started_at = $run->started_at ?? now();
        $run->input_context = array_merge($run->input_context ?? [], [
            'messages' => [],
        ]);
        $run->output = null;
        $run->save();

        $this->publishEvent('ai.run.started', $run, [
            'status' => 'running',
            'started_at' => optional($run->started_at)->toIso8601String(),
        ]);

        try {
            // Execute first iteration; subsequent iterations self-dispatch via AiRunExecutionJob
            $result = $actions->executeWithEvents($run, function (string $eventType, array $data) use ($run): void {
                $this->publishEvent($eventType, $run, $data);
            });

            $output = $result['output'];
            $hasMore = (bool) data_get($output, 'hasMoreIterations', false);

            if ($hasMore) {
                // More tool calls needed — self-dispatch for next iteration
                self::dispatch($this->runId, $this->tenantId);
            } else {
                // No more iterations — finalize the run
                $actions->finalizeRun($run, $output, function (string $eventType, array $data) use ($run): void {
                    $this->publishEvent($eventType, $run, $data);
                });
                $run->refresh();
                $this->publishDelegationResult($run);
            }
        } catch (\Throwable $exception) {
            Log::error('Autopilot run failed', [
                'run_id' => $run->id,
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            $run->status = 'failed';
            $run->output = [
                'error' => $exception->getMessage(),
            ];
            $run->completed_at = now();
            $run->save();

            $this->publishEvent('ai.run.failed', $run, [
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            $this->publishDelegationResult($run);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function publishEvent(string $event, AiAutopilotRun $run, array $extra = []): void
    {
        try {
            Redis::connection('gateway')->publish('ws.events', json_encode([
                'event' => $event,
                'version' => '1.0',
                'data' => [
                    'event_id' => (string) Str::uuid(),
                    'tenant_id' => $run->tenant_id,
                    'run_id' => $run->id,
                    'timestamp' => now()->toIso8601String(),
                    ...$extra,
                ],
            ], JSON_THROW_ON_ERROR));
        } catch (\Throwable $exception) {
            Log::warning('Failed to publish ai autopilot realtime event', [
                'event' => $event,
                'run_id' => (string) $run->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function publishDelegationResult(AiAutopilotRun $run): void
    {
        if (! is_string($run->parent_run_id) || $run->parent_run_id === '') {
            return;
        }

        $key = sprintf('ai:delegation:result:%s', $run->parent_run_id);

        try {
            $payload = json_encode([
                'child_run_id' => (string) $run->id,
                'status' => (string) $run->status,
                'output' => is_array($run->output) ? $run->output : null,
            ], JSON_THROW_ON_ERROR);

            $redis = Redis::connection('gateway');
            $redis->lpush($key, $payload);
            $redis->expire($key, 30);
        } catch (\Throwable $exception) {
            Log::warning('Failed to publish ai delegation result', [
                'run_id' => (string) $run->id,
                'parent_run_id' => (string) $run->parent_run_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
