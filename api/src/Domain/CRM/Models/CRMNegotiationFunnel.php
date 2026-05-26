<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMNegotiationFunnelFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Funil de vendas para negociações CRM.
 *
 * Define a estrutura de pipeline com etapas para rastrear
 * o progresso das negociações no processo comercial.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 */
class CRMNegotiationFunnel extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_negotiation_funnels';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<CRMNegotiationFunnelStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(CRMNegotiationFunnelStep::class, 'crm_negotiation_funnel_id');
    }

    protected static function newFactory(): CRMNegotiationFunnelFactory
    {
        return CRMNegotiationFunnelFactory::new();
    }
}
