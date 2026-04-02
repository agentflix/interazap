<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Database\Factories\ChatInstanceFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de Instância de Chat (WhatsApp/Omichannel).
 *
 * Representa uma conexão configurada com um provedor de mensageria (ex: Uazapi),
 * armazenando o token de segurança para recebimento de webhooks e vinculação
 * de mensagens ao tenant correto.
 *
 * @property string $id Identificador UUID.
 * @property string $tenant_id Identificador do tenant proprietário.
 * @property string $provider Nome do provedor (ex: uazapi).
 * @property string $name Nome amigável da instância.
 * @property string $mode Modo de operação.
 * @property string $status Status de conexão atual.
 * @property bool $is_active Indica se a instância está habilitada para uso.
 * @property string $webhook_token Token único para validação de eventos de entrada.
 * @property array $settings_json Configurações técnicas específicas da instância.
 * @property \Illuminate\Support\Carbon|null $last_status_at Data da última atualização de status.
 * @property \Illuminate\Support\Carbon $created_at Data de criação.
 * @property \Illuminate\Support\Carbon $updated_at Data de atualização.
 *
 * @category Models
 */
class ChatInstance extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'chat_instances';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'provider',
        'name',
        'mode',
        'status',
        'is_active',
        'evaluation_enabled',
        'evaluation_cutoff_score',
        'webhook_token',
        'settings_json',
        'last_status_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'evaluation_enabled' => 'boolean',
        'evaluation_cutoff_score' => 'integer',
        'settings_json' => 'array',
        'last_status_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $instance): void {
            if (! $instance->id) {
                $instance->id = (string) \Illuminate\Support\Str::orderedUuid();
            }
        });
    }

    protected static function newFactory(): ChatInstanceFactory
    {
        return ChatInstanceFactory::new();
    }
}
