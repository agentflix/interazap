<?php

declare(strict_types=1);

namespace Domain\Chat\Jobs;

use Domain\Chat\Services\ChatAutoReplyResponder;
use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Executes Auto Reply response asynchronously.
 */
final class ChatAutoReplyRespondJob implements ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $tenantId  Tenant identifier.
     * @param  string  $ticketId  Ticket identifier.
     * @param  string  $body  Incoming message body.
     * @param  bool  $isFirstInteraction  Whether this is the first ticket interaction.
     */
    public function __construct(
        private readonly string $tenantId,
        private readonly string $ticketId,
        private readonly string $body,
        private readonly bool $isFirstInteraction = false,
    ) {
        $this->onQueue('auto-reply');
    }

    /**
     * Handle auto reply response generation.
     */
    public function handle(ChatAutoReplyResponder $responder): void
    {
        $responder->respond(
            $this->tenantId,
            $this->ticketId,
            $this->body,
            $this->isFirstInteraction,
        );
    }
}
