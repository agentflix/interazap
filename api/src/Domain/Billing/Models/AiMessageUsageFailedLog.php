<?php

declare(strict_types=1);

namespace Domain\Billing\Models;

use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Model representing failed AI message usage attempts for reconciliation.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $ai_turn_id
 * @property string $channel
 * @property \Carbon\Carbon $attempted_at
 * @property string|null $reason
 * @property \Carbon\Carbon|null $reconciled_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read PlatformTenant $tenant
 */
final class AiMessageUsageFailedLog extends Model
{
    protected $table = 'ai_message_usage_failed_log';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'ai_turn_id',
        'channel',
        'attempted_at',
        'reason',
        'reconciled_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'attempted_at' => 'datetime',
        'reconciled_at' => 'datetime',
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
}
