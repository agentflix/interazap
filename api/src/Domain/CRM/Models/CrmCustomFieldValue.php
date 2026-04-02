<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMCustomFieldValueFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Value of a Custom Field for CRM entities.
 *
 * Stores the actual value of a custom field for a specific
 * entity (contact, company, or negotiation).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $crm_custom_field_id
 * @property string $entity_type
 * @property string $entity_id
 * @property mixed $value
 */
class CRMCustomFieldValue extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_custom_field_values';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'crm_custom_field_id',
        'entity_type',
        'entity_id',
        'value',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $value): void {
            if (! $value->id) {
                $value->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CRMCustomField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(CRMCustomField::class, 'crm_custom_field_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): CRMCustomFieldValueFactory
    {
        return CRMCustomFieldValueFactory::new();
    }
}
