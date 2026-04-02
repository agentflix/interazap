<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Skill/Function enabled for an AI Agent.
 *
 * Represents a specific capability or function that an agent
 * can use during conversation execution.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $agent_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property array|null $metadata
 */
class AiAgentSkill extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_agent_skills';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'agent_id',
        'name',
        'description',
        'is_active',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
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
