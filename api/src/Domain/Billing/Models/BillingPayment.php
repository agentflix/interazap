<?php

declare(strict_types=1);

namespace Domain\Billing\Models;

use Domain\Billing\Enums\BillingPaymentStatus;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Model para representar Pagamentos no domínio Billing.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property float $amount
 * @property string $payment_method
 * @property string $provider
 * @property string $provider_payment_id
 * @property BillingPaymentStatus $status
 * @property \Carbon\Carbon|null $confirmed_at
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read PlatformTenant $tenant
 * @property-read BillingInvoice $invoice
 */
class BillingPayment extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'billing_payments';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'invoice_id',
        'amount',
        'payment_method',
        'provider',
        'provider_payment_id',
        'status',
        'confirmed_at',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'status' => BillingPaymentStatus::class,
        'confirmed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (! $payment->id) {
                $payment->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Fatura relacionada ao pagamento.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'invoice_id');
    }
}
