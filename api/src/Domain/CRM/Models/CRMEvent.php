<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMEventFactory;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Calendar Event in CRM (multi-tenant).
 *
 * Represents events such as meetings, calls, tasks, and deadlines
 * with support for participants, reminders, and recurrence.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $auth_user_id
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property bool $is_all_day
 * @property string $status
 * @property string $type
 * @property string $recurrence
 * @property \Illuminate\Support\Carbon|null $recurrence_ends_at
 * @property string|null $color
 */
class CRMEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_CALL = 'call';

    public const TYPE_TASK = 'task';

    public const TYPE_DEADLINE = 'deadline';

    public const TYPE_REMINDER = 'reminder';

    public const TYPE_OTHER = 'other';

    public const RECURRENCE_NONE = 'none';

    public const RECURRENCE_DAILY = 'daily';

    public const RECURRENCE_WEEKLY = 'weekly';

    public const RECURRENCE_MONTHLY = 'monthly';

    public const RECURRENCE_YEARLY = 'yearly';

    protected $table = 'crm_events';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'auth_user_id',
        'title',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'is_all_day',
        'status',
        'type',
        'recurrence',
        'recurrence_ends_at',
        'color',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'recurrence_ends_at' => 'datetime',
            'is_all_day' => 'boolean',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Domain\Auth\Models\AuthUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'auth_user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<CRMEventLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(CRMEventLink::class, 'crm_event_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<CRMEventParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(CRMEventParticipant::class, 'crm_event_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<CRMEventReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(CRMEventReminder::class, 'crm_event_id');
    }

    /**
     * @return array<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_SCHEDULED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_MEETING,
            self::TYPE_CALL,
            self::TYPE_TASK,
            self::TYPE_DEADLINE,
            self::TYPE_REMINDER,
            self::TYPE_OTHER,
        ];
    }

    /**
     * @return array<string>
     */
    public static function recurrences(): array
    {
        return [
            self::RECURRENCE_NONE,
            self::RECURRENCE_DAILY,
            self::RECURRENCE_WEEKLY,
            self::RECURRENCE_MONTHLY,
            self::RECURRENCE_YEARLY,
        ];
    }

    /**
     * Criar uma nova instância da Factory para testes.
     */
    protected static function newFactory(): CRMEventFactory
    {
        return CRMEventFactory::new();
    }
}
