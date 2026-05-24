<?php

declare(strict_types=1);

namespace Domain\Billing\Models;

use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Model representing per-cycle message usage for a tenant.
 *
 * @property string $id
 * @property string $tenant_id
 * @property \Carbon\Carbon $cycle_start
 * @property \Carbon\Carbon $cycle_end
 * @property int $message_count
 * @property int $overage_count
 * @property \Carbon\Carbon|null $alert_80_sent_at
 * @property \Carbon\Carbon|null $alert_100_sent_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read PlatformTenant $tenant
 */
final class TenantMessageUsage extends Model
{
    protected $table = 'tenant_message_usage';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'cycle_start',
        'cycle_end',
        'message_count',
        'overage_count',
        'alert_80_sent_at',
        'alert_100_sent_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'cycle_start' => 'date',
        'cycle_end' => 'date',
        'message_count' => 'integer',
        'overage_count' => 'integer',
        'alert_80_sent_at' => 'datetime',
        'alert_100_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            if (! $model->id) {
                $model->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * @return BelongsTo<PlatformTenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PlatformTenant::class, 'tenant_id');
    }

    /**
     * Scope: usage row for a specific tenant in the active cycle window.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForActiveCycle(Builder $query, string $tenantId, string $cycleStart): void
    {
        $query->where('tenant_id', $tenantId)->where('cycle_start', $cycleStart);
    }
}
