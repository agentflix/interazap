<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource de serialização de preferências de notificação do usuário.
 */
final class ConfigurationNotificationPreferenceResource extends BaseJsonResource
{
    /**
     * Retorna os atributos da preferência de notificação para serialização.
     *
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'notification_type' => $this->notification_type,
            'channels' => $this->channels,
            'enabled' => $this->enabled,
            'quiet_start' => $this->quiet_start?->format('H:i'),
            'quiet_end' => $this->quiet_end?->format('H:i'),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
