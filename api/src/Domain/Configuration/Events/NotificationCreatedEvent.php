<?php

declare(strict_types=1);

namespace Domain\Configuration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado após criação de uma notificação para um canal.
 */
final class NotificationCreatedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  string  $notificationId  Identificador da notificação persistida.
     * @param  string  $channel  Canal de destino.
     */
    public function __construct(
        public readonly string $notificationId,
        public readonly string $channel,
    ) {}
}
