<?php

declare(strict_types=1);

namespace Domain\Chat\Jobs;

use Domain\Chat\Actions\ChatUazapiWebhookActions;
use Domain\Chat\Actions\ChatWebhookIngestor;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Context;

/**
 * Async webhook ingress job for normalized provider payloads.
 */
final class ChatWebhookIngressJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 60;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $descriptor
     */
    public function __construct(
        private readonly string $tenantId,
        private readonly array $payload,
        private readonly array $descriptor,
    ) {}

    public function uniqueId(): string
    {
        return $this->descriptor['message_id'] ?? $this->tenantId.'_'.md5(json_encode($this->payload));
    }

    public function handle(ChatWebhookIngestor $ingestor, ChatBroadcastService $broadcast): void
    {
        Context::add([
            'tenant_id' => $this->tenantId,
            'provider' => 'uazapi',
            'event_type' => (string) ($this->payload['event_type'] ?? 'unknown'),
            'instance_id' => (string) ($this->payload['instance_id'] ?? ''),
        ]);

        $ingestor->ingest($this->tenantId, $this->payload);
        ChatUazapiWebhookActions::emitRealtimeStatic($broadcast, $this->payload, $this->descriptor);
    }
}
