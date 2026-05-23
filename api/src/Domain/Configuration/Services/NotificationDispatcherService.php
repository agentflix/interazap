<?php

declare(strict_types=1);

namespace Domain\Configuration\Services;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Actions\ConfigurationNotificationActions;
use Domain\Configuration\Events\NotificationCreatedEvent;
use Domain\Configuration\Jobs\SendNotificationJob;
use Domain\Configuration\Models\ConfigurationNotification;
use Domain\Configuration\Models\ConfigurationNotificationPreference;
use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Support\Facades\Cache;

/**
 * Serviço central de despacho de notificações multi-canal.
 */
final class NotificationDispatcherService
{
    private const int DEBOUNCE_TTL_MINUTES = 5;

    private const int DIGEST_THRESHOLD = 5;

    private const int DIGEST_LOCK_TTL_MINUTES = 1;

    public function __construct(
        private readonly ConfigurationNotificationActions $actions,
        private readonly GatewayBroadcastService $broadcast,
    ) {}

    /**
     * Despacha notificações respeitando preferências e quiet hours.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string|array<int, string>  $userIds  Destinatários ou '*' para todo o tenant.
     * @param  string  $type  Tipo de notificação.
     * @param  string  $title  Título da notificação.
     * @param  string  $body  Conteúdo textual.
     * @param  array<string, mixed>  $data  Dados extras (link, entity_id etc).
     * @param  string  $priority  urgent|high|normal|low.
     */
    public function dispatch(
        string $tenantId,
        string|array $userIds,
        string $type,
        string $title,
        string $body,
        array $data = [],
        string $priority = 'normal',
    ): void {
        $recipients = $this->resolveRecipients($tenantId, $userIds);

        foreach ($recipients as $recipientId) {
            $preference = $this->resolvePreference($tenantId, $recipientId, $type);

            if (! $preference->enabled) {
                continue;
            }

            $channels = $preference->channels;
            if (! is_array($channels) || $channels === []) {
                $channels = ['ui'];
            }

            foreach ($channels as $channel) {
                if (! is_string($channel)) {
                    continue;
                }

                if ($this->shouldDebounce($tenantId, $recipientId, $type, $channel, $data)) {
                    continue;
                }

                [$resolvedTitle, $resolvedBody, $resolvedData] = $this->resolveDigestPayload(
                    tenantId: $tenantId,
                    userId: $recipientId,
                    type: $type,
                    priority: $priority,
                    originalTitle: $title,
                    originalBody: $body,
                    originalData: $data,
                );

                if ($resolvedTitle === null || $resolvedBody === null || $resolvedData === null) {
                    continue;
                }

                $isQuietHours = $preference->isQuietHours();
                $status = $isQuietHours ? ConfigurationNotification::STATUS_PENDING : ConfigurationNotification::STATUS_SENT;

                $notification = $this->actions->create(
                    tenantId: $tenantId,
                    userId: $recipientId,
                    type: $type,
                    title: $resolvedTitle,
                    body: $resolvedBody,
                    channel: $channel,
                    data: [
                        ...$resolvedData,
                        'priority' => $priority,
                        'quiet_hours_blocked' => $isQuietHours,
                    ],
                    status: $status,
                );

                if ($isQuietHours) {
                    continue;
                }

                SendNotificationJob::dispatch((string) $notification->id, $channel, $tenantId);
                NotificationCreatedEvent::dispatch((string) $notification->id, $channel);

                // Broadcast via WebSocket for UI channel
                if ($channel === 'ui') {
                    $this->broadcast->broadcastEvent('notification.new', [
                        'tenant_id' => $tenantId,
                        'user_id' => $recipientId,
                        'notification' => [
                            'id' => $notification->id,
                            'type' => $type,
                            'title' => $resolvedTitle,
                            'body' => $resolvedBody,
                            'data' => $resolvedData,
                            'priority' => $priority,
                            'created_at' => $notification->created_at?->toIso8601String(),
                        ],
                    ], "tenant:{$tenantId}");
                }
            }
        }
    }

    /**
     * @param  string|array<int, string>  $userIds
     * @return array<int, string>
     */
    private function resolveRecipients(string $tenantId, string|array $userIds): array
    {
        if ($userIds === '*') {
            return AuthUser::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all();
        }

        return array_values(array_unique(array_map(
            static fn (string $id): string => (string) $id,
            array_filter($userIds, static fn (string $id): bool => $id !== '')
        )));
    }

    private function resolvePreference(string $tenantId, string $userId, string $type): ConfigurationNotificationPreference
    {
        $preference = ConfigurationNotificationPreference::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('notification_type', $type)
            ->first();

        if ($preference !== null) {
            return $preference;
        }

        return new ConfigurationNotificationPreference([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'notification_type' => $type,
            'channels' => ['ui'],
            'enabled' => true,
        ]);
    }

    /**
     * Aplicar debounce por entidade para evitar spam de notificações repetidas.
     *
     * @param  array<string, mixed>  $data
     */
    private function shouldDebounce(string $tenantId, string $userId, string $type, string $channel, array $data): bool
    {
        $entityType = data_get($data, 'entity_type');
        $entityId = data_get($data, 'entity_id');

        if (! is_string($entityType) || $entityType === '' || ! is_string($entityId) || $entityId === '') {
            return false;
        }

        $key = sprintf(
            'notification:debounce:%s:%s:%s:%s:%s:%s',
            $tenantId,
            $userId,
            $type,
            $channel,
            $entityType,
            $entityId,
        );

        return ! Cache::add($key, '1', now()->addMinutes(self::DEBOUNCE_TTL_MINUTES));
    }

    /**
     * Resolver payload em modo digest quando o volume excede o limite por minuto.
     *
     * @param  array<string, mixed>  $originalData
     * @return array{0: ?string, 1: ?string, 2: ?array<string, mixed>}
     */
    private function resolveDigestPayload(
        string $tenantId,
        string $userId,
        string $type,
        string $priority,
        string $originalTitle,
        string $originalBody,
        array $originalData,
    ): array {
        if ($priority === 'urgent') {
            return [$originalTitle, $originalBody, $originalData];
        }

        $counterKey = sprintf('notification:digest:counter:%s:%s:%s', $tenantId, $userId, $type);
        $count = (int) Cache::increment($counterKey);

        if ($count === 1) {
            Cache::put($counterKey, 1, now()->addMinute());
        }

        if ($count <= self::DIGEST_THRESHOLD) {
            return [$originalTitle, $originalBody, $originalData];
        }

        $lockKey = sprintf('notification:digest:lock:%s:%s:%s', $tenantId, $userId, $type);
        $canDispatchDigest = Cache::add($lockKey, '1', now()->addMinutes(self::DIGEST_LOCK_TTL_MINUTES));

        if (! $canDispatchDigest) {
            return [null, null, null];
        }

        return [
            'Resumo de notificações',
            'Você recebeu múltiplas atualizações em sequência. Abra a central para ver os detalhes.',
            [
                ...$originalData,
                'digest_mode' => true,
                'digest_count' => $count,
            ],
        ];
    }
}
