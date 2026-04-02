<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMContactPhoneFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contact phone number history (append-only with validity period).
 *
 * Stores phone numbers associated with a CRM contact,
 * with validity periods for tracking changes over time.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $crm_contact_id
 * @property string|null $label
 * @property string $phone_e164
 * @property bool $is_primary
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_to
 */
class CRMContactPhone extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_contact_phones';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'crm_contact_id',
        'label',
        'phone_e164',
        'is_primary',
        'valid_from',
        'valid_to',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CRMContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CRMContact::class, 'crm_contact_id');
    }

    protected static function newFactory(): CRMContactPhoneFactory
    {
        return CRMContactPhoneFactory::new();
    }
}
