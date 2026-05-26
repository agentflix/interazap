<?php

declare(strict_types=1);

namespace Domain\Chat\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando o webhook `message_template_status_update` da Meta é
 * normalizado pelo Gateway e roteado ao bounded context Chat.
 *
 * Carrega tudo o necessário para localizar o `chat_message_templates` local
 * por (chat_instance_id, name, language) ou (chat_instance_id, external_id)
 * e atualizar `status` + `rejected_reason`.
 */
final class MetaTemplateStatusUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $instanceId,
        public readonly string $templateName,
        public readonly string $language,
        public readonly string $status,
        public readonly ?string $rejectedReason = null,
        public readonly ?string $externalId = null,
    ) {}

    /**
     * Canal privado do tenant para atualizações de templates.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->tenantId.'.templates')];
    }

    /** Nome do evento broadcast no frontend. */
    public function broadcastAs(): string
    {
        return 'chat.template.updated';
    }

    /**
     * Dados enviados ao frontend junto com o evento broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'template_name' => $this->templateName,
            'language' => $this->language,
            'status' => $this->status,
            'rejected_reason' => $this->rejectedReason,
            'external_id' => $this->externalId,
        ];
    }
}
