<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Domain\Chat\Models\ChatTicket;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Event link to CRM/Chat entities.
 *
 * Associates a calendar event with related entities such as
 * contacts, companies, deals, or tickets.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $crm_event_id
 * @property string $linkable_type
 * @property string $linkable_id
 */
class CRMEventLink extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'crm_event_links';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'crm_event_id',
        'linkable_type',
        'linkable_id',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CRMEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CRMEvent::class, 'crm_event_id');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, class-string>
     */
    public static function linkableTypes(): array
    {
        return [
            'contact' => CRMContact::class,
            'company' => CRMCompany::class,
            'deal' => CRMNegotiation::class,
            'ticket' => ChatTicket::class,
        ];
    }

    public static function resolveLinkableType(string $alias): ?string
    {
        return self::linkableTypes()[$alias] ?? null;
    }
}
