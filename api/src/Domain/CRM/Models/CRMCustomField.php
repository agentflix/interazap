<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMCustomFieldFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Definição de campo personalizado para entidades CRM.
 *
 * Permite que o tenant configure campos dinâmicos (texto, número, data, seleção etc.)
 * associados a contatos, empresas ou negociações.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $type
 * @property string $entity
 * @property array|null $options
 * @property bool $is_required
 */
class CRMCustomField extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_custom_fields';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'type',
        'entity',
        'options',
        'is_required',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $field): void {
            if (! $field->id) {
                $field->id = (string) Str::orderedUuid();
            }
        });
    }

    protected static function newFactory(): CRMCustomFieldFactory
    {
        return CRMCustomFieldFactory::new();
    }
}
