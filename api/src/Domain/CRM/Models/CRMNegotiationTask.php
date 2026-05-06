<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMNegotiationTaskFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Task linked to a Negotiation.
 *
 * Represents a to-do item or action item associated with
 * a CRM negotiation deal.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $crm_negotiation_id
 * @property string|null $auth_user_id
 * @property string $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property \Domain\CRM\Enums\CRMTaskStatus $status
 */
class CRMNegotiationTask extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_negotiation_tasks';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'crm_negotiation_id',
        'auth_user_id',
        'title',
        'description',
        'due_date',
        'status',
        'action_type',
        'start_time',
        'end_time',
        'reminder_at',
        'add_to_agenda',
        'agenda_event_id',
        'notify_ui',
        'notify_email',
        'notify_push',
        'notify_whatsapp',
        'is_completed',
        'completed_at',
        'priority',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'due_date' => 'datetime',
        'status' => \Domain\CRM\Enums\CRMTaskStatus::class,
        'start_time' => 'string',
        'end_time' => 'string',
        'reminder_at' => 'datetime',
        'add_to_agenda' => 'boolean',
        'notify_ui' => 'boolean',
        'notify_email' => 'boolean',
        'notify_push' => 'boolean',
        'notify_whatsapp' => 'boolean',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<CRMNegotiation, $this>
     */
    public function negotiation(): BelongsTo
    {
        return $this->belongsTo(CRMNegotiation::class, 'crm_negotiation_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Domain\Auth\Models\AuthUser, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\Domain\Auth\Models\AuthUser::class, 'auth_user_id');
    }

    protected static function newFactory(): CRMNegotiationTaskFactory
    {
        return CRMNegotiationTaskFactory::new();
    }
}
