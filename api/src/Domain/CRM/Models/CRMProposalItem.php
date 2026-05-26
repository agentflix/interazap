<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMProposalItemFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de linha de uma proposta comercial CRM.
 *
 * Representa um produto ou serviço adicionado à proposta com
 * quantidade, preço unitário, desconto e total calculado.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $crm_proposal_id
 * @property string|null $crm_product_id
 * @property string $name
 * @property int $quantity
 * @property float $unit_price
 * @property float $discount
 * @property float $total
 * @property int $position
 */
class CRMProposalItem extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_proposal_items';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'crm_proposal_id',
        'crm_product_id',
        'name',
        'quantity',
        'unit_price',
        'discount',
        'total',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'position' => 'integer',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CRMProposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(CRMProposal::class, 'crm_proposal_id');
    }

    protected static function newFactory(): CRMProposalItemFactory
    {
        return CRMProposalItemFactory::new();
    }
}
