<?php

declare(strict_types=1);

namespace Domain\Billing\Models;

use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Model para representar relatórios de purge de tenant.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tenant_name
 * @property string|null $tenant_document
 * @property string|null $tenant_email
 * @property array<string, mixed> $summary
 * @property array<int, mixed> $invoices_snapshot
 * @property \Carbon\Carbon $purged_at
 * @property string $purged_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read PlatformTenant $tenant
 */
class BillingPurgeReport extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'billing_purge_reports';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'tenant_name',
        'tenant_document',
        'tenant_email',
        'summary',
        'invoices_snapshot',
        'purged_at',
        'purged_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'summary' => 'array',
        'invoices_snapshot' => 'array',
        'purged_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $report): void {
            if (! $report->id) {
                $report->id = (string) Str::orderedUuid();
            }
        });
    }
}
