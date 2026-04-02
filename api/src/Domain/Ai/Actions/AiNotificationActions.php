<?php

declare(strict_types=1);

namespace Domain\Ai\Actions;

use Domain\Ai\Enums\AiNotificationChannel;
use Domain\Ai\Enums\AiNotificationReason;
use Domain\Ai\Models\AiSellerNotification;
use Domain\Chat\Models\ChatTicket;
use Domain\Configuration\Events\AiEscalationRequiredEvent;
use Domain\Configuration\Events\AiHotLeadDetectedEvent;
use Domain\Configuration\Events\StorageLimitWarningEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Redis;

/**
 * Actions para notificações de vendedores da IA.
 */
final class AiNotificationActions
{
    /**
     * Lista notificações do usuário atual.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $userId  Identificador do vendedor.
     * @param  bool  $unreadOnly  Filtra apenas notificações não lidas.
     * @return LengthAwarePaginator<int, AiSellerNotification>
     */
    public function list(string $tenantId, string $userId, bool $unreadOnly = false): LengthAwarePaginator
    {
        $query = AiSellerNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('seller_id', $userId)
            ->orderByDesc('created_at');

        if ($unreadOnly) {
            $query->whereNull('delivered_at');
        }

        return $query->paginate(20);
    }

    /**
     * Buscar notificação específica do usuário.
     */
    public function find(string $tenantId, string $userId, string $id): ?AiSellerNotification
    {
        return AiSellerNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('seller_id', $userId)
            ->where('id', $id)
            ->first();
    }

    /**
     * Marcar notificações como lidas.
     *
     * @param  array<int, string>  $ids
     */
    public function markAsRead(string $tenantId, string $userId, array $ids = []): int
    {
        $query = AiSellerNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('seller_id', $userId)
            ->whereNull('delivered_at');

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }

        return $query->update(['delivered_at' => now()]);
    }

    /**
     * Contar notificações não lidas.
     */
    public function unreadCount(string $tenantId, string $userId): int
    {
        return AiSellerNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('seller_id', $userId)
            ->whereNull('delivered_at')
            ->count();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyHotLead(string $tenantId, string $title, string $message, array $data = []): void
    {
        AiHotLeadDetectedEvent::dispatch($tenantId, $title, $message, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyEscalationRequired(string $tenantId, string $title, string $message, array $data = []): void
    {
        AiEscalationRequiredEvent::dispatch($tenantId, $title, $message, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyStorageLimitWarning(string $tenantId, string $title, string $message, array $data = []): void
    {
        StorageLimitWarningEvent::dispatch($tenantId, $title, $message, $data);
    }

    /**
     * Criar alerta de sentimento crítico para o responsável do ticket.
     */
    public function createSentimentAlert(ChatTicket $ticket, int $score): void
    {
        $lockKey = "sentiment:alert:{$ticket->id}";
        $redis = Redis::connection();

        $lock = $redis instanceof PredisConnection
            ? $redis->client()->executeRaw(['SET', $lockKey, '1', 'EX', '3600', 'NX'])
            : $redis->set($lockKey, '1', ['EX' => 3600, 'NX']);

        if ($lock !== true && $lock !== 'OK') {
            return;
        }

        if (! $ticket->assigned_to) {
            return;
        }

        $notification = AiSellerNotification::query()->create([
            'tenant_id' => (string) $ticket->tenant_id,
            'seller_id' => (string) $ticket->assigned_to,
            'notifiable_type' => ChatTicket::class,
            'notifiable_id' => (string) $ticket->id,
            'message' => sprintf(
                'Ticket #%s com sentimento crítico (score: %d/100). Cliente pode estar em risco de churn.',
                (string) ($ticket->protocol ?? $ticket->id),
                $score,
            ),
            'reason' => AiNotificationReason::NEGATIVE_SENTIMENT,
            'channel' => AiNotificationChannel::INTERNAL,
            'priority' => 'urgent',
            'metadata' => [
                'sentiment_score' => $score,
            ],
        ]);

        Redis::connection('gateway')->publish('ws.events', json_encode([
            'event' => 'notification.sent',
            'data' => [
                'tenant_id' => (string) $ticket->tenant_id,
                'notification_id' => (string) $notification->id,
                'reason' => AiNotificationReason::NEGATIVE_SENTIMENT->value,
                'seller_id' => (string) $ticket->assigned_to,
            ],
        ]));
    }
}
