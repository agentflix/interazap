<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMNegotiationFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Negociação (Deal) no CRM.
 *
 * Representa uma oportunidade de venda em andamento, rastreada através
 * de um funil de vendas e suas etapas, com valor monetário e status.
 *
 * @category Models
 */
class CRMNegotiation extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'crm_negotiations';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'auth_user_id',
        'crm_company_id',
        'crm_contact_id',
        'crm_negotiation_funnel_id',
        'crm_negotiation_funnel_step_id',
        'crm_reason_loss_id',
        'title',
        'amount',
        'notes',
        'status',
        'lead_score',
        'position',
        'expected_close',
        'closed_at',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'lead_score' => 'integer',
        'position' => 'integer',
        'expected_close' => 'date',
        'closed_at' => 'datetime',
        'status' => \Domain\CRM\Enums\CRMNegotiationStatus::class,
    ];

    /**
     * Relacionamento com o Usuário responsável.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\Domain\Auth\Models\AuthUser::class, 'auth_user_id');
    }

    /**
     * Relacionamento com a Empresa.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CRMCompany::class, 'crm_company_id');
    }

    /**
     * Relacionamento com o Contato principal.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CRMContact::class, 'crm_contact_id');
    }

    /**
     * Relacionamento com o Funil de Vendas.
     */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(CRMNegotiationFunnel::class, 'crm_negotiation_funnel_id');
    }

    /**
     * Relacionamento com a Etapa do Funil.
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(CRMNegotiationFunnelStep::class, 'crm_negotiation_funnel_step_id');
    }

    /**
     * Relacionamento com Tarefas da negociação.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(CRMNegotiationTask::class, 'crm_negotiation_id');
    }

    /**
     * Relacionamento com Propostas comerciais.
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(CRMProposal::class, 'crm_negotiation_id');
    }

    /**
     * Relacionamento com Produtos/Itens da negociação.
     */
    public function products(): HasMany
    {
        return $this->hasMany(CRMNegotiationProduct::class, 'crm_negotiation_id');
    }

    /**
     * Relacionamento com Arquivos anexados.
     */
    public function files(): HasMany
    {
        return $this->hasMany(CRMNegotiationFile::class, 'crm_negotiation_id');
    }

    /**
     * Relacionamento com Tags.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CRMTag::class, 'crm_negotiation_tags', 'crm_negotiation_id', 'crm_tag_id')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /**
     * Relacionamento com o Motivo de Perda (se perdida).
     */
    public function reasonLoss(): BelongsTo
    {
        return $this->belongsTo(CRMReasonLoss::class, 'crm_reason_loss_id');
    }

    /**
     * Valores de campos personalizados.
     */
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CRMCustomFieldValue::class, 'entity')->with('field');
    }

    /**
     * Histórico de notas da negociação.
     */
    public function historyNotes(): MorphMany
    {
        return $this->morphMany(CRMNote::class, 'entity');
    }

    /**
     * Criar uma nova instância da Factory para testes.
     */
    protected static function newFactory(): CRMNegotiationFactory
    {
        return CRMNegotiationFactory::new();
    }
}
