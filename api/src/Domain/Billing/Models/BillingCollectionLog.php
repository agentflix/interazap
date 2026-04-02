<?php

declare(strict_types=1);

namespace Domain\Billing\Models;

use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Model para representar logs de cobrança no domínio Billing.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property string $template_id
 * @property string $channel
 * @property string $recipient
 * @property string $status
 * @property string|null $provider_message_id
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read PlatformTenant $tenant
 * @property-read BillingInvoice $invoice
 */
class BillingCollectionLog extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'billing_collection_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'invoice_id',
        'template_id',
        'channel',
        'recipient',
        'status',
        'provider_message_id',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            if (! $log->id) {
                $log->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Fatura relacionada ao evento de cobrança.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }
}
