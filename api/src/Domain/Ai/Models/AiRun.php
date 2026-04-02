<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Model for AI completion runs.
 *
 * Tracks the status and results of AI completion requests
 * processed asynchronously via jobs.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $status
 * @property string $prompt
 * @property string|null $output
 * @property string|null $model
 * @property int|null $tokens_used
 * @property string|null $finish_reason
 * @property string|null $error
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AiRun extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'status',
        'prompt',
        'output',
        'model',
        'tokens_used',
        'finish_reason',
        'error',
        'metadata',
        'started_at',
        'completed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tokens_used' => 'integer',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * Initialize the model.
     */
    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            if (! $run->id) {
                $run->id = (string) Str::orderedUuid();
            }
            if (! $run->status) {
                $run->status = self::STATUS_PENDING;
            }
        });
    }

    /**
     * Check if the run is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the run is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if the run is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the run has failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
