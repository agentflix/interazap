<?php

declare(strict_types=1);

namespace Domain\Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento genérico de Broadcast em tempo real.
 *
 * Permite emitir eventos customizados para múltiplos canais privados
 * (ex: tenant.{id}, ticket.{id}) sem acoplamento a uma classe específica.
 */
final class RealtimeBroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<string>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly array $channels,
        private readonly string $eventName,
        private readonly array $payload,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (string $channel) => new PrivateChannel($channel),
            $this->channels
        );
    }

    public function broadcastAs(): string
    {
        return $this->eventName;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
