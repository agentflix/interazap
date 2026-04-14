<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Domain\CRM\Models\CRMContact;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * ChatSession model for webchat sessions.
 *
 * Manages anonymous or authenticated webchat sessions, tracking visitor
 * activity and linking to a contact and ticket for the conversation.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $contact_id
 * @property string $ticket_id
 * @property string $token
 * @property array|null $client_info
 * @property Carbon|null $last_activity_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ChatSession extends Model
{
    use BelongsToTenant;

    protected $table = 'chat_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'contact_id',
        'ticket_id',
        'token',
        'client_info',
        'last_activity_at',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'client_info' => 'array',
        'last_activity_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            if (! $session->id) {
                $session->id = (string) Str::orderedUuid();
            }
            if (! $session->token) {
                $session->token = (string) Str::uuid();
            }
        });
    }

    /**
     * Relationship with the ChatTicket.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ChatTicket::class, 'ticket_id');
    }

    /**
     * Relationship with the CRMContact.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CRMContact::class, 'contact_id');
    }

    /**
     * Update last activity timestamp.
     */
    public function touchLastActivity(): void
    {
        $this->last_activity_at = now();
        $this->save();
    }
}
