<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMCompanyFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Company (Client) in CRM.
 *
 * Represents a business entity in the CRM system, supporting contacts,
 * deals, tags, and custom fields.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $document
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $zip_code
 * @property bool $is_active
 */
class CRMCompany extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'crm_companies';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'document',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zip_code',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<CRMContact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(
            CRMContact::class,
            'crm_company_contacts',
            'crm_company_id',
            'crm_contact_id'
        )
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<CRMTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CRMTag::class, 'crm_company_tags', 'crm_company_id', 'crm_tag_id')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<CRMCustomFieldValue, $this>
     */
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CRMCustomFieldValue::class, 'entity')->with('field');
    }

    protected static function newFactory(): CRMCompanyFactory
    {
        return CRMCompanyFactory::new();
    }
}
