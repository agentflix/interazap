<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMProductFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produto ou Serviço do CRM.
 *
 * Item cadastrado no catálogo que pode ser adicionado a negociações
 * para compor o valor total e gerar propostas.
 *
 * @category Models
 */
class CRMProduct extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_products';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'code',
        'description',
        'type',
        'price',
        'cost',
        'unit',
        'stock_quantity',
        'min_stock',
        'is_active',
        'is_featured',
        'track_stock',
        'stock',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'stock_quantity' => 'integer',
        'min_stock' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'track_stock' => 'boolean',
        'stock' => 'integer',
    ];

    /**
     * Relacionamento com itens de negociação (uso deste produto).
     */
    public function negotiationProducts(): HasMany
    {
        return $this->hasMany(CRMNegotiationProduct::class, 'crm_product_id');
    }

    /**
     * Criar uma nova instância da Factory para testes.
     */
    protected static function newFactory(): CRMProductFactory
    {
        return CRMProductFactory::new();
    }
}
