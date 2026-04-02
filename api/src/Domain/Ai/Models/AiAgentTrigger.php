<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Trigger configuration for an AI Agent.
 *
 * Defines conditions that activate an agent during a conversation,
 * such as keywords, intent classification, or event-based triggers.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $agent_id
 * @property string $type
 * @property array|null $config
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $last_run_at
 */
class AiAgentTrigger extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_agent_triggers';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'agent_id',
        'type',
        'config',
        'status',
        'last_run_at',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'config' => 'array',
        'last_run_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->id) {
                $item->id = (string) Str::orderedUuid();
            }
        });
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }
}
