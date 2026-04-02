<?php

declare(strict_types=1);

namespace Domain\Shared\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Modelo placeholder para políticas de mídia compartilhada.
 *
 * @property string $id
 */
final class SharedMedia extends Model
{
    use BelongsToTenant;

    protected $table = 'shared_media';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $media): void {
            if (! $media->id) {
                $media->id = (string) Str::orderedUuid();
            }
        });
    }
}
