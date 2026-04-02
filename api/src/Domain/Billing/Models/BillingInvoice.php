<?php

declare(strict_types=1);

namespace Domain\Billing\Models;

use Database\Factories\BillingInvoiceFactory;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Model para representar Faturas no domínio Billing.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $plan_id
 * @property string $reference_month
 * @property float $amount
 * @property BillingInvoiceStatus $status
 * @property \Carbon\Carbon $due_date
 * @property \Carbon\Carbon|null $paid_at
 * @property string|null $payment_method
 * @property string|null $payment_url
 * @property string|null $asaas_payment_id
 * @property string|null $pix_payload
 * @property string|null $pix_qr_code_base64
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read PlatformTenant $tenant
 * @property-read PlatformPlan|null $plan
 * @property-read \Illuminate\Database\Eloquent\Collection<BillingPayment> $payments
 */
class BillingInvoice extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'billing_invoices';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'plan_id',
        'reference_month',
        'amount',
        'status',
        'due_date',
        'paid_at',
        'payment_method',
        'payment_url',
        'asaas_payment_id',
        'pix_payload',
        'pix_qr_code_base64',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'status' => BillingInvoiceStatus::class,
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            if (! $invoice->id) {
                $invoice->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Criar uma nova instância da Factory para testes.
     */
    protected static function newFactory(): BillingInvoiceFactory
    {
        return BillingInvoiceFactory::new();
    }

    /**
     * Plano associado à fatura.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformPlan::class, 'plan_id');
    }

    /**
     * Pagamentos recebidos para esta fatura.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class, 'invoice_id');
    }

    /**
     * Verifica se a fatura já foi paga.
     */
    public function isPaid(): bool
    {
        return $this->status === BillingInvoiceStatus::PAID;
    }

    /**
     * Verifica se a fatura pode ser paga.
     */
    public function canBePaid(): bool
    {
        return in_array($this->status, [
            BillingInvoiceStatus::DRAFT,
            BillingInvoiceStatus::PENDING,
            BillingInvoiceStatus::OVERDUE,
        ], true);
    }

    /**
     * Marca a fatura como paga.
     */
    public function markAsPaid(): void
    {
        $this->status = BillingInvoiceStatus::PAID;
        $this->paid_at = now();
        $this->save();
    }
}
