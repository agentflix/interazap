<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMNoteFactory;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Nota ou anotação para entidades CRM.
 *
 * Representa um comentário textual vinculado por polimorfismo a
 * contatos, empresas ou negociações, incluindo o histórico de alterações.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $entity_type
 * @property string $entity_id
 * @property string|null $auth_user_id
 * @property string $content
 */
class CRMNote extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_notes';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'entity_type',
        'entity_id',
        'auth_user_id',
        'content',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $note): void {
            if (! $note->id) {
                $note->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Domain\Auth\Models\AuthUser, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'auth_user_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): CRMNoteFactory
    {
        return CRMNoteFactory::new();
    }
}
