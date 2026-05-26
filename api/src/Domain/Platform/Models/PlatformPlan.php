<?php

declare(strict_types=1);

namespace Domain\Platform\Models;

use Database\Factories\PlatformPlanFactory;
use Domain\Billing\Enums\OverageMode;
use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformReportsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Plano de assinatura da plataforma.
 *
 * Define os tiers de contratação com limites de usuários, armazenamento,
 * canais de chat, negociações e funcionalidades como IA e excedentes.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property int|null $limit_users
 * @property \Domain\Platform\Enums\PlatformStorageMode $storage_mode
 * @property int|null $storage_limit_bytes
 * @property bool $ai_enabled
 * @property int $message_limit_monthly
 * @property \Domain\Billing\Enums\OverageMode $overage_mode
 * @property float|null $overage_price_per_message
 * @property int|null $message_retention_days
 * @property int|null $chat_channels_limit
 * @property \Domain\Platform\Enums\PlatformNegotiationsMode $negotiations_mode
 * @property int|null $negotiations_limit
 * @property \Domain\Platform\Enums\PlatformReportsMode $reports_mode
 * @property float|null $price_monthly
 * @property string|null $asaas_product_id
 * @property int $cycle_days
 * @property bool $is_trial
 * @property bool $is_active
 */
final class PlatformPlan extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Tabela associada ao modelo.
     */
    protected $table = 'platform_plans';

    /**
     * Chave primária não auto-incrementa (UUID).
     */
    public $incrementing = false;

    /**
     * Tipo da chave primária.
     */
    protected $keyType = 'string';

    /**
     * Atributos atribuíveis em massa.
     *
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
        'message_limit_monthly',
        'overage_mode',
        'overage_price_per_message',
        'message_retention_days',
        'chat_channels_limit',
        'negotiations_mode',
        'negotiations_limit',
        'reports_mode',
        'price_monthly',
        'cycle_days',
        'is_trial',
        'asaas_product_id',
        'is_active',
    ];

    /**
     * Casts de atributos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'limit_users' => 'integer',
        'storage_mode' => PlatformStorageMode::class,
        'storage_limit_bytes' => 'integer',
        'ai_enabled' => 'boolean',
        'message_limit_monthly' => 'integer',
        'overage_mode' => OverageMode::class,
        'overage_price_per_message' => 'decimal:4',
        'message_retention_days' => 'integer',
        'chat_channels_limit' => 'integer',
        'negotiations_mode' => PlatformNegotiationsMode::class,
        'negotiations_limit' => 'integer',
        'reports_mode' => PlatformReportsMode::class,
        'price_monthly' => 'decimal:2',
        'cycle_days' => 'integer',
        'is_trial' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Hooks do ciclo de vida do modelo.
     */
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

    /**
     * Verifica se o plano tem limite de armazenamento configurado.
     */
    public function isStorageLimited(): bool
    {
        return $this->storage_mode === PlatformStorageMode::LIMITED;
    }

    /**
     * Verifica se o plano tem limite de negociações configurado.
     */
    public function isNegotiationsLimited(): bool
    {
        return $this->negotiations_mode === PlatformNegotiationsMode::LIMITED;
    }

    /**
     * Cria uma nova instância da Factory para testes.
     */
    protected static function newFactory(): PlatformPlanFactory
    {
        return PlatformPlanFactory::new();
    }
}
