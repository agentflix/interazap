<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMNegotiationFileFactory;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * File attached to a Negotiation.
 *
 * Represents a document or file uploaded and linked to a CRM negotiation.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $crm_negotiation_id
 * @property string|null $auth_user_id
 * @property string $name
 * @property string $path
 * @property int $size
 * @property string|null $mime_type
 */
class CRMNegotiationFile extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_negotiation_files';

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
        'name',
        'path',
        'size',
        'mime_type',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $file): void {
            if (! $file->id) {
                $file->id = (string) Str::orderedUuid();
            }
        });
    }

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
    public function author(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'auth_user_id');
    }

    protected static function newFactory(): CRMNegotiationFileFactory
    {
        return CRMNegotiationFileFactory::new();
    }
}
