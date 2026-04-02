<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Auth\Models\AuthUser;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * File content attached to an AI Agent.
 *
 * Stores configurable file content (knowledge base) that the agent
 * can access during conversations.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $agent_id
 * @property string $slug
 * @property string $content
 * @property string|null $updated_by
 */
class AiAgentFile extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_agent_files';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'agent_id',
        'slug',
        'content',
        'updated_by',
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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'updated_by');
    }
}
