<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMNegotiationProductFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de produto vinculado a uma negociação CRM.
 *
 * Representa uma linha de produto em uma negociação, armazenando
 * quantidade, preço unitário e valor total calculado.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $crm_negotiation_id
 * @property string|null $crm_product_id
 * @property string $name
 * @property int $quantity
 * @property float $unit_price
 * @property float $total
 */
class CRMNegotiationProduct extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_negotiation_products';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'crm_negotiation_id',
        'crm_product_id',
        'name',
        'quantity',
        'unit_price',
        'total',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CRMNegotiation, $this>
     */
    public function negotiation(): BelongsTo
    {
        return $this->belongsTo(CRMNegotiation::class, 'crm_negotiation_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CRMProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(CRMProduct::class, 'crm_product_id');
    }

    protected static function newFactory(): CRMNegotiationProductFactory
    {
        return CRMNegotiationProductFactory::new();
    }
}
