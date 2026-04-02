<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMContactFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contato do CRM.
 *
 * Entidade que representa uma pessoa (lead, cliente, parceiro) na base de dados,
 * centralizando informações de contato, tags e campos personalizados.
 *
 * @category Models
 */
class CRMContact extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'crm_contacts';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'crm_company_id',
        'name',
        'email',
        'document',
        'phone',
        'whatsapp',
        'avatar_url',
        'position',
        'notes',
        'custom_fields',
        'is_active',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'custom_fields' => 'array',
    ];

    /**
     * Relacionamento com a Empresa principal vinculada.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CRMCompany::class, 'crm_company_id');
    }

    /**
     * Relacionamento com telefones adicionais.
     */
    public function phones(): HasMany
    {
        return $this->hasMany(CRMContactPhone::class, 'crm_contact_id');
    }

    /**
     * Relacionamento many-to-many com Empresas.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(
            CRMCompany::class,
            'crm_company_contacts',
            'crm_contact_id',
            'crm_company_id'
        )
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /**
     * Relacionamento com Tags.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CRMTag::class, 'crm_contact_tags', 'crm_contact_id', 'crm_tag_id')
            ->withPivot('id', 'tenant_id')
            ->withTimestamps();
    }

    /**
     * Valores de campos personalizados associados.
     */
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CRMCustomFieldValue::class, 'entity')->with('field');
    }

    /**
     * Negociações (Deals) vinculadas a este contato.
     */
    public function negotiations(): HasMany
    {
        return $this->hasMany(CRMNegotiation::class, 'crm_contact_id');
    }

    /**
     * Criar uma nova instância da Factory para testes.
     */
    protected static function newFactory(): CRMContactFactory
    {
        return CRMContactFactory::new();
    }
}
