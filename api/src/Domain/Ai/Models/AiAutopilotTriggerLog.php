<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Log de disparo de gatilhos do Autopilot.
 */
class AiAutopilotTriggerLog extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_autopilot_trigger_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'trigger_type',
        'source_id',
        'source_type',
        'playbook_id',
        'run_id',
        'status',
        'context',
        'skip_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->id) {
                $item->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }
}
