<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Database\Factories\ChatInstanceFactory;
use Domain\Platform\Models\PlatformTenant;
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
        'auto_close_enabled',
        'auto_close_after_minutes',
        'auto_close_target',
        'auto_close_message',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'evaluation_enabled' => 'boolean',
        'evaluation_cutoff_score' => 'integer',
        'settings_json' => 'array',
        'last_status_at' => 'datetime',
        // auto_close_enabled and auto_close_after_minutes are NOT cast —
        // they must preserve null to support "herdar do tenant".
        // Nullable boolean/integer cast converters (bool) null → false, (int) null → 0,
        // destroying the null semantics needed by getEffectiveAutoCloseConfig().
        'auto_close_target' => 'string',
        'auto_close_message' => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $instance): void {
            if (! $instance->id) {
                $instance->id = (string) \Illuminate\Support\Str::orderedUuid();
            }
        });
    }

    /**
     * Resolve a configuração efetiva de auto-fechamento, resolvendo null → tenant.
     *
     * @return array{enabled: bool, minutes: int, target: string, message: string}
     */
    public function getEffectiveAutoCloseConfig(PlatformTenant $tenant): array
    {
        $chatSettings = $tenant->settings_chat ?? [];

        return [
            'enabled' => $this->auto_close_enabled ?? $chatSettings['auto_close_inactivity_enabled'] ?? false,
            'minutes' => $this->auto_close_after_minutes ?? $chatSettings['auto_close_inactivity_minutes'] ?? 30,
            'target' => $this->auto_close_target ?? $chatSettings['auto_close_inactivity_target'] ?? 'both',
            'message' => $this->auto_close_message ?? $chatSettings['auto_close_inactivity_message'] ?? 'Este atendimento foi encerrado automaticamente por inatividade. Caso precise de mais ajuda, por favor inicie um novo atendimento.',
        ];
    }

    protected static function newFactory(): ChatInstanceFactory
    {
        return ChatInstanceFactory::new();
    }
}
