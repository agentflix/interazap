<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Domain\Ai\Actions\AiNotificationActions;
use Domain\Ai\Enums\SentimentLevel;
use Domain\Ai\Services\AiSentimentService;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Job assíncrono para análise de sentimento por ticket.
 */
final class AiAnalyzeSentimentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public readonly string $ticketId,
        public readonly string $messageContent,
        public readonly string $tenantId,
    ) {}

    public function handle(AiSentimentService $service, AiNotificationActions $notificationActions): void
    {
        $lockKey = "sentiment:cooldown:{$this->ticketId}";
        $lock = true;

        try {
            $redis = Redis::connection();
            $lock = $redis instanceof PredisConnection
                ? $redis->client()->executeRaw(['SET', $lockKey, '1', 'EX', '30', 'NX'])
                : $redis->set($lockKey, '1', ['EX' => 30, 'NX']);
        } catch (Throwable) {
            $lock = true;
        }

        if ($lock !== true && $lock !== 'OK') {
            return;
        }

        $ticket = ChatTicket::query()
            ->where('tenant_id', $this->tenantId)
            ->find($this->ticketId);

        if (! $ticket) {
            return;
        }

        try {
            $result = $service->analyze($this->messageContent, $this->tenantId);
        } catch (Throwable) {
            return;
        }

        $previousScore = $ticket->sentiment_score;
        $newScore = $previousScore !== null
            ? (int) round(((float) $previousScore * 0.4) + ((float) $result->score * 0.6))
            : $result->score;

        $newLevel = SentimentLevel::fromScore($newScore);

        $ticket->update([
            'sentiment' => $newLevel->value,
            'sentiment_score' => $newScore,
            'sentiment_updated_at' => now(),
        ]);

        try {
            Redis::connection('gateway')->publish('ws.events', json_encode([
                'event' => 'ticket.sentiment_updated',
                'data' => [
                    'tenant_id' => $this->tenantId,
                    'ticket_id' => $this->ticketId,
                    'sentiment' => $newLevel->value,
                    'sentiment_score' => $newScore,
                ],
            ]));
        } catch (Throwable) {
        }

        if ($newScore > 80) {
            $notificationActions->createSentimentAlert($ticket, $newScore);
        }
    }
}
