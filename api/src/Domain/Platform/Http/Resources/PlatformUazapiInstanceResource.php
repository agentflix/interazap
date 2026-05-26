<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource de serialização de instância Uazapi.
 */
final class PlatformUazapiInstanceResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'system_name' => $this->system_name,
            'has_token' => $this->token !== null && $this->token !== '',
            'token_preview' => $this->token ? '****'.substr($this->token, -4) : null,
            'status' => $this->status,
            'webhook_url' => $this->webhook_url,
            'config' => $this->config ?? [],
            'metadata' => $this->metadata,
            'last_status_at' => $this->iso($this->last_status_at),
            'last_seen_at' => $this->iso($this->last_status_at),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
