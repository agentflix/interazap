<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Carbon\Carbon;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Ai\Models\AiUsageLog;
use Domain\Chat\Services\ChatAiActivityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AiRunTrackerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload, private readonly ?ChatAiActivityService $chatAiActivity = null) {}

    public function handle(): void
    {
        $runId = (string) ($this->payload['run_id'] ?? '');
        $tenantId = (string) ($this->payload['tenant_id'] ?? '');

        if ($runId === '' || $tenantId === '') {
            return;
        }

        $updatedRun = null;

        DB::transaction(function () use ($runId, $tenantId, &$updatedRun): void {
            $run = AiAutopilotRun::query()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->find($runId);

            if (! $run instanceof AiAutopilotRun) {
                return;
            }

            if (in_array((string) $run->status, ['completed', 'failed'], true)) {
                return;
            }

            $run->status = (string) ($this->payload['status'] ?? $run->status);
            $run->output = is_array($this->payload['output'] ?? null) ? $this->payload['output'] : $run->output;
            if ($this->isTerminalStatus((string) $run->status)) {
                $run->completed_at = $this->resolveCompletedAt($run->completed_at);
            }
            $run->save();

            $this->createUsageLog($run);

            $updatedRun = $run;
        });

        if ($updatedRun instanceof AiAutopilotRun) {
            $this->emitChatLifecycleEvent($updatedRun);
        }
    }

    public function failed(Throwable $exception): void
    {
        $runId = (string) ($this->payload['run_id'] ?? '');
        $tenantId = (string) ($this->payload['tenant_id'] ?? '');

        if ($runId === '' || $tenantId === '') {
            return;
        }

        $run = AiAutopilotRun::query()
            ->where('tenant_id', $tenantId)
            ->find($runId);

        if (! $run instanceof AiAutopilotRun) {
            return;
        }

        if (in_array((string) $run->status, ['completed', 'failed'], true)) {
            return;
        }

        $output = is_array($run->output) ? $run->output : [];
        $output['error'] = $exception->getMessage();
        $output['error_code'] = 'tracker_exhausted';

        $run->status = 'failed';
        $run->output = $output;
        $run->completed_at = $run->completed_at ?? now();
        $run->save();
    }

    private function isTerminalStatus(string $status): bool
    {
        return in_array($status, ['completed', 'failed', 'blocked', 'cancelled', 'rejected'], true);
    }

    private function resolveCompletedAt(?Carbon $fallback): Carbon
    {
        $payloadTimestamp = $this->payload['completed_at'] ?? null;

        if (is_string($payloadTimestamp) && $payloadTimestamp !== '') {
            try {
                return \Illuminate\Support\Facades\Date::parse($payloadTimestamp);
            } catch (\Throwable) {
            }
        }

        return $fallback ?? now();
    }

    private function createUsageLog(AiAutopilotRun $run): void
    {
        $payload = $this->payload;
        $output = is_array($run->output) ? $run->output : [];
        $raw = is_array($output['raw'] ?? null) ? $output['raw'] : [];

        $inputTokens = (int) ($raw['prompt_tokens'] ?? 0);
        $outputTokens = (int) ($raw['completion_tokens'] ?? 0);

        // Fallback: try to read from payload top-level
        if ($inputTokens === 0) {
            $inputTokens = (int) ($payload['prompt_tokens'] ?? 0);
            $outputTokens = (int) ($payload['completion_tokens'] ?? 0);
        }

        AiUsageLog::query()->create([
            'tenant_id' => (string) $run->tenant_id,
            'model_name' => (string) ($raw['model'] ?? $payload['model'] ?? 'unknown'),
            'provider' => 'openai',
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cached_prompt_tokens' => (int) ($raw['cached_prompt_tokens'] ?? 0),
            'input_cost' => (float) ($raw['input_cost'] ?? 0),
            'output_cost' => (float) ($raw['output_cost'] ?? 0),
            'feature' => 'autopilot.run',
            'usable_type' => AiAutopilotRun::class,
            'usable_id' => (string) $run->id,
            'metadata' => [
                'playbook_id' => (string) $run->playbook_id,
                'status' => (string) $run->status,
            ],
        ]);
    }

    private function emitChatLifecycleEvent(AiAutopilotRun $run): void
    {
        $activity = $this->chatAiActivity ?? app(ChatAiActivityService::class);
        $ticketId = (string) data_get($run->input_context, 'ticket_id', '');

        if ($ticketId === '') {
            return;
        }

        $messageId = (string) data_get($run->input_context, 'message_id', '');
        $completedAt = $run->completed_at?->toIso8601String();

        if ((string) $run->status === 'completed') {
            $activity->emitProcessingCompleted(
                (string) $run->tenant_id,
                $ticketId,
                (string) $run->id,
                $messageId,
                ['completed_at' => $completedAt]
            );

            return;
        }

        if ((string) $run->status === 'failed') {
            $activity->emitProcessingFailed(
                (string) $run->tenant_id,
                $ticketId,
                (string) $run->id,
                (string) data_get($run->output, 'error', 'AI run failed.'),
                $messageId,
                ['completed_at' => $completedAt]
            );

            return;
        }

        if (in_array((string) $run->status, ['blocked', 'cancelled', 'rejected'], true)) {
            $activity->emitProcessingRejected(
                (string) $run->tenant_id,
                $ticketId,
                (string) $run->id,
                (string) data_get($run->output, 'error', ucfirst((string) $run->status)),
                $messageId,
                [
                    'completed_at' => $completedAt,
                    'source_status' => (string) $run->status,
                ]
            );
        }
    }
}
