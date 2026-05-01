<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource para serialização de token de dispositivo.
 */
final class AuthDeviceTokenResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'platform' => $this->platform,
            'token' => $this->token,
            'device_name' => $this->device_name,
            'last_active_at' => $this->iso($this->last_active_at),
            'revoked_at' => $this->iso($this->revoked_at),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
