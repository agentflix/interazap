<?php

declare(strict_types=1);

namespace Domain\Platform\DTOs;

use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO para criação e atualização de planos da plataforma.
 *
 * Transporta os dados validados de um plano entre as camadas HTTP e de domínio.
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
        public ?int $tokenLimitMonthly,
        public bool $allowOverage,
        public ?float $overagePricePer1k,
        public int $chatChannelsLimit,
        public PlatformNegotiationsMode $negotiationsMode,
        public ?int $negotiationsLimit,
        public float $priceMonthly,
        public ?bool $isActive = null,
    ) {}

    /**
     * Cria o DTO a partir de um FormRequest validado.
     *
     * @param  FormRequest  $request  Requisição validada.
     * @return self DTO preenchido.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Cria o DTO a partir de um array de dados.
     *
     * @param  array<string, mixed>  $data  Dados do plano.
     * @return self DTO preenchido.
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
            tokenLimitMonthly: isset($data['token_limit_monthly']) ? (int) $data['token_limit_monthly'] : null,
            allowOverage: (bool) ($data['allow_overage'] ?? false),
            overagePricePer1k: isset($data['overage_price_per_1k']) ? (float) $data['overage_price_per_1k'] : null,
            chatChannelsLimit: (int) ($data['chat_channels_limit'] ?? 0),
            negotiationsMode: PlatformNegotiationsMode::from((string) ($data['negotiations_mode'] ?? 'disabled')),
            negotiationsLimit: isset($data['negotiations_limit']) ? (int) $data['negotiations_limit'] : null,
            priceMonthly: (float) ($data['price_monthly'] ?? 0),
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    /**
     * Converte o DTO em array para persistência no banco.
     *
     * @return array<string, mixed> Representação em array do plano.
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
            'token_limit_monthly' => $this->tokenLimitMonthly,
            'allow_overage' => $this->allowOverage,
            'overage_price_per_1k' => $this->overagePricePer1k,
            'chat_channels_limit' => $this->chatChannelsLimit,
            'negotiations_mode' => $this->negotiationsMode->value,
            'negotiations_limit' => $this->negotiationsLimit,
            'price_monthly' => $this->priceMonthly,
            'is_active' => $this->isActive,
        ];
    }
}
