<?php

declare(strict_types=1);

namespace Domain\Configuration\Jobs;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Configuration\Mail\NotificationMail;
use Domain\Configuration\Models\ConfigurationNotification;
use Domain\Configuration\Models\ConfigurationNotificationWebhook;
use Domain\Configuration\Models\ConfigurationPushSubscription;
use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Job responsável por entregar notificação para o canal configurado.
 */
final class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Número máximo de tentativas antes de falhar definitivamente. */
    public int $tries = 3;

    /**
     * Backoff progressivo entre tentativas em segundos (10s, 60s, 300s).
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /** Máximo de exceções não capturadas antes de marcar como falho. */
    public int $maxExceptions = 2;

    /**
     * Cria o job com os identificadores necessários para recuperar e enviar a notificação.
     *
     * @param  string  $notificationId  Identificador UUID da notificação a enviar.
     * @param  string  $channel  Canal alvo (ui|email|push|whatsapp|webhook).
     * @param  string  $tenantId  Identificador do tenant proprietário.
     */
    public function __construct(
        public readonly string $notificationId,
        public readonly string $channel,
        public readonly string $tenantId,
    ) {}

    /**
     * Executa a entrega da notificação no canal configurado.
     *
     * @param  GatewayBroadcastService  $broadcastService  Serviço de broadcast WebSocket.
     * @param  ChatGatewayService  $chatGatewayService  Serviço de envio WhatsApp.
     */
    public function handle(
        GatewayBroadcastService $broadcastService,
        ChatGatewayService $chatGatewayService,
    ): void {
        $notification = ConfigurationNotification::query()->where('tenant_id', $this->tenantId)->find($this->notificationId);

        if (! $notification) {
            return;
        }

        try {
            match ($this->channel) {
                'ui' => $this->sendViaUi($notification, $broadcastService),
                'email' => $this->sendViaEmail($notification),
                'push' => $this->sendViaPush($notification, $broadcastService),
                'whatsapp' => $this->sendViaWhatsApp($notification, $chatGatewayService),
                'webhook' => $this->sendViaWebhook($notification),
                default => $notification->markAsFailed('Unsupported notification channel'),
            };
        } catch (\Throwable $exception) {
            $notification->markAsFailed($exception->getMessage());
        }
    }

    /** Entrega a notificação via WebSocket para o canal de interface do usuário. */
    private function sendViaUi(ConfigurationNotification $notification, GatewayBroadcastService $broadcastService): void
    {
        $payload = [
            'tenant_id' => (string) $notification->tenant_id,
            'notification' => [
                'id' => (string) $notification->id,
                'user_id' => $notification->user_id,
                'type' => $notification->type,
                'title' => $notification->title,
                'body' => $notification->body,
                'data' => $notification->data,
                'channel' => $notification->channel,
                'status' => $notification->status,
                'created_at' => $notification->created_at?->toISOString(),
                'sent_at' => now()->toISOString(),
            ],
        ];

        $broadcastService->broadcastEvent(
            event: 'notification:new',
            data: $payload,
            room: 'tenant:'.(string) $notification->tenant_id,
        );

        $broadcastService->broadcastEvent(
            event: 'notification.sent',
            data: $payload,
            room: 'tenant:'.(string) $notification->tenant_id,
        );

        $notification->markAsSent();
    }

    /** Entrega a notificação por e-mail ao destinatário do registro. */
    private function sendViaEmail(ConfigurationNotification $notification): void
    {
        $recipient = AuthUser::query()
            ->where('tenant_id', $notification->tenant_id)
            ->where('id', $notification->user_id)
            ->first();

        if (! $recipient || $recipient->email === '') {
            $notification->markAsFailed('Recipient email not found');

            return;
        }

        Mail::to($recipient->email)->send(new NotificationMail($notification));

        $notification->markAsSent();
    }

    /** Entrega a notificação push web via broadcast para as assinaturas ativas do usuário. */
    private function sendViaPush(ConfigurationNotification $notification, GatewayBroadcastService $broadcastService): void
    {
        $subscriptions = ConfigurationPushSubscription::query()
            ->where('tenant_id', $notification->tenant_id)
            ->where('user_id', $notification->user_id)
            ->where('is_active', true)
            ->get();

        if ($subscriptions->isEmpty()) {
            $notification->markAsFailed('No active push subscription found');

            return;
        }

        $broadcastService->broadcastEvent(
            event: 'notification.push',
            data: [
                'tenant_id' => (string) $notification->tenant_id,
                'notification_id' => (string) $notification->id,
                'user_id' => $notification->user_id,
                'title' => $notification->title,
                'body' => $notification->body,
                'data' => $notification->data,
                'subscriptions_count' => $subscriptions->count(),
            ],
            room: 'tenant:'.(string) $notification->tenant_id,
        );

        $notification->markAsSent();
    }

    /** Entrega a notificação via WhatsApp para o número do destinatário. */
    private function sendViaWhatsApp(ConfigurationNotification $notification, ChatGatewayService $chatGatewayService): void
    {
        $recipient = AuthUser::query()
            ->where('tenant_id', $notification->tenant_id)
            ->where('id', $notification->user_id)
            ->first();

        $phone = $this->normalizePhone(is_string($recipient?->phone) ? $recipient->phone : null);
        if ($phone === null) {
            $notification->markAsFailed('Recipient phone not found');

            return;
        }

        $instance = ChatInstance::query()
            ->where('tenant_id', $notification->tenant_id)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->first();

        if (! $instance) {
            $notification->markAsFailed('No active WhatsApp instance found');

            return;
        }

        $settings = $instance->settings_json;
        $token = $settings['token'] ?? $instance->webhook_token;

        if (! is_string($token) || $token === '') {
            $notification->markAsFailed('WhatsApp instance token not configured');

            return;
        }

        $message = trim($notification->title."\n\n".($notification->body ?? ''));
        $chatGatewayService->sendText($token, [
            'number' => $phone,
            'text' => $message,
        ]);

        $notification->markAsSent();
    }

    /** Entrega a notificação via HTTP POST para todos os webhooks ativos do tenant. */
    private function sendViaWebhook(ConfigurationNotification $notification): void
    {
        $webhooks = ConfigurationNotificationWebhook::query()
            ->where('tenant_id', $notification->tenant_id)
            ->where('is_active', true)
            ->get();

        if ($webhooks->isEmpty()) {
            $notification->markAsFailed('No active webhook configured');

            return;
        }

        $payload = [
            'id' => (string) $notification->id,
            'tenant_id' => (string) $notification->tenant_id,
            'user_id' => $notification->user_id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'channel' => $notification->channel,
            'status' => $notification->status,
            'data' => $notification->data,
            'created_at' => $notification->created_at?->toISOString(),
        ];

        $delivered = false;

        foreach ($webhooks as $webhook) {
            $eventTypes = $webhook->event_types;
            if (is_array($eventTypes) && $eventTypes !== [] && ! in_array($notification->type, $eventTypes, true)) {
                continue;
            }

            $headers = [
                'Content-Type' => 'application/json',
                'X-Notification-Id' => (string) $notification->id,
                'X-Notification-Type' => $notification->type,
            ];

            if (is_string($webhook->secret) && $webhook->secret !== '') {
                $headers['X-Notification-Signature'] = hash_hmac('sha256', json_encode($payload) ?: '{}', $webhook->secret);
            }

            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->post($webhook->url, $payload);

            if ($response->successful()) {
                $delivered = true;
                $webhook->update([
                    'failure_count' => 0,
                    'last_success_at' => now(),
                ]);

                continue;
            }

            $webhook->update([
                'failure_count' => (int) $webhook->failure_count + 1,
                'last_failure_at' => now(),
            ]);
        }

        if (! $delivered) {
            $notification->markAsFailed('All webhook deliveries failed');

            return;
        }

        $notification->markAsSent();
    }

    /**
     * Normaliza o número de telefone removendo caracteres não numéricos.
     *
     * @param  string|null  $phone  Número bruto.
     * @return string|null Apenas dígitos ou null se inválido.
     */
    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (! is_string($digits) || $digits === '') {
            return null;
        }

        return $digits;
    }
}
