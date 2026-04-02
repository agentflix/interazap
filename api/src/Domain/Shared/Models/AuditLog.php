<?php

declare(strict_types=1);

namespace Domain\Shared\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Modelo representativo de um registro de auditoria.
 *
 * Armazena o histórico imutável de alterações realizadas no sistema,
 * incluindo o snapshot dos dados modificados.
 *
 * @category Models
 */
final class AuditLog extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'audit_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'user_type',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'url',
        'ip_address',
        'user_agent',
        'tags',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Inicializar o modelo e definir comportamentos automáticos.
     *
     * Registra o evento de criação para gerar automaticamente o UUID
     * caso não tenha sido fornecido.
     */
    protected static function booted(): void
    {
        self::creating(function (self $log): void {
            if (! $log->id) {
                $log->id = (string) Str::orderedUuid();
            }
        });
    }
}
