<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Domain\Auth\Models\AuthUser;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Event participant (user or contact).
 *
 * Represents a person invited to a calendar event,
 * tracking their attendance status.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $crm_event_id
 * @property string|null $auth_user_id
 * @property string|null $crm_contact_id
 * @property string|null $name
 * @property string|null $email
 * @property string $status
 * @property bool $is_organizer
 */
class CRMEventParticipant extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    protected $table = 'crm_event_participants';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'crm_event_id',
        'auth_user_id',
        'crm_contact_id',
        'name',
        'email',
        'status',
        'is_organizer',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'is_organizer' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CRMEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CRMEvent::class, 'crm_event_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Domain\Auth\Models\AuthUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'auth_user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CRMContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CRMContact::class, 'crm_contact_id');
    }
}
