<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMReasonLossFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Motivo de perda de negociação CRM.
 *
 * Armazena motivos pré-definidos e customizados pelo tenant para indicar
 * por que uma negociação foi perdida, habilitando análise e melhorias.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property bool $requires_comment
 * @property bool $is_active
 * @property int $position
 * @property int $usage_count
 */
class CRMReasonLoss extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_reason_losses';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'description',
        'requires_comment',
        'is_active',
        'position',
        'usage_count',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'requires_comment' => 'boolean',
        'is_active' => 'boolean',
        'position' => 'integer',
        'usage_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->id) {
                $item->id = (string) Str::orderedUuid();
            }
        });
    }

    protected static function newFactory(): CRMReasonLossFactory
    {
        return CRMReasonLossFactory::new();
    }
}
