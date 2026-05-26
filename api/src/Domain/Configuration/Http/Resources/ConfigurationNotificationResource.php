<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource de serialização de registros de notificação.
 */
final class ConfigurationNotificationResource extends BaseJsonResource
{
    /**
     * Retorna os atributos da notificação para serialização.
     *
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'channel' => $this->channel,
            'status' => $this->status,
            'sent_at' => $this->iso($this->sent_at),
            'read_at' => $this->iso($this->read_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
