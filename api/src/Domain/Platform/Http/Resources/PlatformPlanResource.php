<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Resources;

use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Platform Plan serialization.
 */
final class PlatformPlanResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        $storageMode = $this->storage_mode instanceof PlatformStorageMode
            ? $this->storage_mode->value
            : (string) $this->storage_mode;
        $negotiationsMode = $this->negotiations_mode instanceof PlatformNegotiationsMode
            ? $this->negotiations_mode->value
            : (string) $this->negotiations_mode;

        $storageLimitBytes = $this->storage_limit_bytes ? (int) $this->storage_limit_bytes : null;
        $storageLimitGb = $storageLimitBytes ? round($storageLimitBytes / 1024 / 1024 / 1024, 2) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'limit_users' => (int) $this->limit_users,
            'storage_mode' => $storageMode,
            'storage_limit_bytes' => $storageLimitBytes,
            'storage_limit_gb' => $storageLimitGb,
            'ai_enabled' => (bool) $this->ai_enabled,
            'token_limit_monthly' => $this->token_limit_monthly !== null ? (int) $this->token_limit_monthly : null,
            'allow_overage' => (bool) $this->allow_overage,
            'overage_price_per_1k' => $this->overage_price_per_1k !== null ? (string) $this->overage_price_per_1k : null,
            'chat_channels_limit' => (int) $this->chat_channels_limit,
            'negotiations_mode' => $negotiationsMode,
            'negotiations_limit' => $this->negotiations_limit ? (int) $this->negotiations_limit : null,
            'price_monthly' => (string) $this->price_monthly,
            'asaas_product_id' => $this->asaas_product_id,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
