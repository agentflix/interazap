<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource de serialização das configurações de agendamento.
 */
final class ConfigurationSchedulingSettingResource extends BaseJsonResource
{
    /**
     * Retorna os atributos das configurações de agendamento para serialização.
     *
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'event_confirmation_advance_minutes' => (int) $this->event_confirmation_advance_minutes,
            'event_confirmation_enabled' => (bool) $this->event_confirmation_enabled,
            'event_confirmation_notify_ui' => (bool) $this->event_confirmation_notify_ui,
            'event_confirmation_notify_push' => (bool) $this->event_confirmation_notify_push,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
