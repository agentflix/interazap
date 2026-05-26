<?php

declare(strict_types=1);

namespace Domain\Chat\Notifications;

use Domain\Auth\Models\AuthUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Apn\ApnMessage;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * Push de nova mensagem para usuários do tenant (APNs + FCM).
 */
final class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly string $body,
    ) {}

    /**
     * Determina os canais de envio ativos para o usuário (APNs e/ou FCM).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof AuthUser) {
            return [];
        }

        $channels = [];

        if ($this->hasRouteTokens($notifiable->routeNotificationForApn($this))) {
            $channels[] = ApnChannel::class;
        }

        if ($this->hasRouteTokens($notifiable->routeNotificationForFcm($this))) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    /** Constrói o payload de notificação para APNs (iOS). */
    public function toApn(object $notifiable): ApnMessage
    {
        return ApnMessage::create()
            ->badge(1)
            ->title('Nova mensagem')
            ->body($this->messagePreview());
    }

    /** Constrói o payload de notificação para FCM (Android/web). */
    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(notification: new FcmNotification(
            title: 'Nova mensagem',
            body: $this->messagePreview(),
        )))
            ->data([
                'type' => 'chat.new_message',
                'tenant_id' => $this->tenantId,
                'ticket_id' => $this->ticketId,
            ]);
    }

    /**
     * Retorna uma prévia truncada do corpo da mensagem para exibição na notificação.
     */
    private function messagePreview(): string
    {
        $body = trim($this->body);

        if ($body === '') {
            return 'Voce recebeu uma nova mensagem.';
        }

        return Str::limit($body, 140);
    }

    /**
     * Verifica se há tokens de roteamento configurados para o canal.
     *
     * @param  string|array<int, string>  $tokens
     */
    private function hasRouteTokens(string|array $tokens): bool
    {
        if (is_string($tokens)) {
            return $tokens !== '';
        }

        return $tokens !== [];
    }
}
