<?php

declare(strict_types=1);

namespace Domain\Platform\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Instância de Integração Uazapi.
 *
 * Armazena as credenciais e status de conexões com provedores de mensageria
 * vinculadas ao Tenant.
 *
 * @category Models
 */
final class PlatformUazapiInstance extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'platform_uazapi_instances';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'system_name',
        'token',
        'status',
        'webhook_url',
        'config',
        'metadata',
        'last_status_at',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'config' => 'array',
        'metadata' => 'array',
        'last_status_at' => 'datetime',
    ];

    /**
     * Inicializar o modelo e definir comportamentos automáticos.
     */
    protected static function booted(): void
    {
        self::creating(function (self $instance): void {
            if (! $instance->id) {
                $instance->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Relacionamento com o Tenant.
     *
     * @return BelongsTo<PlatformTenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PlatformTenant::class, 'tenant_id');
    }

    /**
     * Cria uma nova instância da Factory para testes.
     */
    protected static function newFactory(): \Database\Factories\PlatformUazapiInstanceFactory
    {
        return \Database\Factories\PlatformUazapiInstanceFactory::new();
    }
}
