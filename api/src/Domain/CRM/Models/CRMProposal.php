<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMProposalFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proposta comercial vinculada a uma negociação CRM.
 *
 * Representa um orçamento formal enviado a um prospect com itens,
 * valores, prazo de validade e token público para visualização externa.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $crm_negotiation_id
 * @property string $title
 * @property string|null $number
 * @property float $total
 * @property \Domain\CRM\Enums\CRMProposalStatus $status
 * @property \Illuminate\Support\Carbon|null $valid_until
 * @property string|null $public_token
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $viewed_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $rejected_at
 */
class CRMProposal extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_proposals';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'crm_negotiation_id',
        'title',
        'number',
        'total',
        'status',
        'valid_until',
        'public_token',
        'notes',
        'sent_at',
        'viewed_at',
        'accepted_at',
        'rejected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'total' => 'decimal:2',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'status' => \Domain\CRM\Enums\CRMProposalStatus::class,
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CRMNegotiation, $this>
     */
    public function negotiation(): BelongsTo
    {
        return $this->belongsTo(CRMNegotiation::class, 'crm_negotiation_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<CRMProposalItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CRMProposalItem::class, 'crm_proposal_id');
    }

    protected static function newFactory(): CRMProposalFactory
    {
        return CRMProposalFactory::new();
    }
}
