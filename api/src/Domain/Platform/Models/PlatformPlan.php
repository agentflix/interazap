<?php

declare(strict_types=1);

namespace Domain\Platform\Models;

use Database\Factories\PlatformPlanFactory;
use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformReportsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Subscription plan for the platform.
 *
 * Defines tiers with limits on users, storage, integrations,
 * and features like AI and negotiations.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property int|null $limit_users
 * @property \Domain\Platform\Enums\PlatformStorageMode $storage_mode
 * @property int|null $storage_limit_bytes
 * @property bool $ai_enabled
 * @property int|null $whatsapp_integrations_limit
 * @property \Domain\Platform\Enums\PlatformNegotiationsMode $negotiations_mode
 * @property int|null $negotiations_limit
 * @property \Domain\Platform\Enums\PlatformReportsMode $reports_mode
 * @property float|null $price_monthly
 * @property string|null $asaas_product_id
 * @property bool $is_active
 */
final class PlatformPlan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'platform_plans';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'slug',
        'limit_users',
        'storage_mode',
        'storage_limit_bytes',
        'ai_enabled',
        'whatsapp_integrations_limit',
        'negotiations_mode',
        'negotiations_limit',
        'reports_mode',
        'price_monthly',
        'asaas_product_id',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'limit_users' => 'integer',
        'storage_mode' => PlatformStorageMode::class,
        'storage_limit_bytes' => 'integer',
        'ai_enabled' => 'boolean',
        'whatsapp_integrations_limit' => 'integer',
        'negotiations_mode' => PlatformNegotiationsMode::class,
        'negotiations_limit' => 'integer',
        'reports_mode' => PlatformReportsMode::class,
        'price_monthly' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $plan): void {
            if (! $plan->id) {
                $plan->id = (string) Str::orderedUuid();
            }

            if (! $plan->slug && $plan->name) {
                $plan->slug = Str::slug($plan->name);
            }
        });
    }

    public function isStorageLimited(): bool
    {
        return $this->storage_mode === PlatformStorageMode::LIMITED;
    }

    public function isNegotiationsLimited(): bool
    {
        return $this->negotiations_mode === PlatformNegotiationsMode::LIMITED;
    }

    protected static function newFactory(): PlatformPlanFactory
    {
        return PlatformPlanFactory::new();
    }
}
