<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMTagFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * CRM Tag.
 *
 * Label/category tag that can be applied to contacts, companies,
 * and negotiations for segmentation and filtering.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $color
 * @property string|null $category
 * @property bool $is_active
 */
class CRMTag extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_tags';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'color',
        'category',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<CRMContact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(CRMContact::class, 'crm_contact_tags', 'crm_tag_id', 'crm_contact_id')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<CRMCompany, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(CRMCompany::class, 'crm_company_tags', 'crm_tag_id', 'crm_company_id')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<CRMNegotiation, $this>
     */
    public function negotiations(): BelongsToMany
    {
        return $this->belongsToMany(CRMNegotiation::class, 'crm_negotiation_tags', 'crm_tag_id', 'crm_negotiation_id')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    protected static function newFactory(): CRMTagFactory
    {
        return CRMTagFactory::new();
    }
}
