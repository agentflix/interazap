<?php

declare(strict_types=1);

namespace Domain\Platform\DTOs;

use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for platform plan creation and update.
 *
 * @readonly
 */
final readonly class PlatformPlanDTO
{
    public function __construct(
        public string $name,
        public ?string $slug,
        public int $limitUsers,
        public PlatformStorageMode $storageMode,
        public ?int $storageLimitBytes,
        public bool $aiEnabled,
        public int $whatsappIntegrationsLimit,
        public PlatformNegotiationsMode $negotiationsMode,
        public ?int $negotiationsLimit,
        public float $priceMonthly,
        public ?bool $isActive = null,
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $slug = $data['slug'] ?? null;

        return new self(
            name: (string) ($data['name'] ?? ''),
            slug: $slug !== null && $slug !== '' ? (string) $slug : null,
            limitUsers: (int) ($data['limit_users'] ?? 0),
            storageMode: PlatformStorageMode::from((string) ($data['storage_mode'] ?? 'default')),
            storageLimitBytes: isset($data['storage_limit_bytes']) ? (int) $data['storage_limit_bytes'] : null,
            aiEnabled: (bool) ($data['ai_enabled'] ?? false),
            whatsappIntegrationsLimit: (int) ($data['whatsapp_integrations_limit'] ?? 0),
            negotiationsMode: PlatformNegotiationsMode::from((string) ($data['negotiations_mode'] ?? 'disabled')),
            negotiationsLimit: isset($data['negotiations_limit']) ? (int) $data['negotiations_limit'] : null,
            priceMonthly: (float) ($data['price_monthly'] ?? 0),
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'limit_users' => $this->limitUsers,
            'storage_mode' => $this->storageMode->value,
            'storage_limit_bytes' => $this->storageLimitBytes,
            'ai_enabled' => $this->aiEnabled,
            'whatsapp_integrations_limit' => $this->whatsappIntegrationsLimit,
            'negotiations_mode' => $this->negotiationsMode->value,
            'negotiations_limit' => $this->negotiationsLimit,
            'price_monthly' => $this->priceMonthly,
            'is_active' => $this->isActive,
        ];
    }
}
