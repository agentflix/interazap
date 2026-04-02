<?php

declare(strict_types=1);

namespace Domain\Chat\Jobs;

use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatWebhookRouter;
use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ConversationResolverJob implements ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly string $tenantId,
        private readonly string $ticketId,
        private readonly string $body,
        private readonly array $context = [],
    ) {}

    public function handle(ChatWebhookRouter $router): void
    {
        $ticket = ChatTicket::query()
            ->where('tenant_id', $this->tenantId)
            ->find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $router->routeInbound($this->tenantId, $ticket, $this->body, $this->context);
    }
}
