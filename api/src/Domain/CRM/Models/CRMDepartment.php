<?php

declare(strict_types=1);

namespace Domain\CRM\Models;

use Database\Factories\CRMDepartmentFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Departamento do CRM.
 *
 * Unidade organizacional para agrupamento de usuários e gestão de
 * permissões dentro do sistema CRM.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 */
class CRMDepartment extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'crm_departments';

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
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): CRMDepartmentFactory
    {
        return CRMDepartmentFactory::new();
    }
}
