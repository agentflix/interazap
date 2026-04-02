<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMNegotiationFunnelStepFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stage of a Sales Funnel.
 *
 * Represents a single step/stage within a negotiation funnel,
 * such as "Lead", "Proposal", "Negotiation", "Closed".
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $crm_negotiation_funnel_id
 * @property string $name
 * @property string|null $color
 * @property bool $is_active
 * @property int $order
 */
class CRMNegotiationFunnelStep extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_negotiation_funnel_steps';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'crm_negotiation_funnel_id',
        'name',
        'color',
        'is_active',
        'order',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CRMNegotiationFunnel, $this>
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(CRMNegotiationFunnel::class, 'crm_negotiation_funnel_id');
    }

    protected static function newFactory(): CRMNegotiationFunnelStepFactory
    {
        return CRMNegotiationFunnelStepFactory::new();
    }
}
