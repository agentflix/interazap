<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de Controle de Cooldown do Auto Reply.
 *
 * Registra o período de silêncio (cooldown) para uma regra específica disparada
 * em um ticket, evitando que o bot responda repetidamente à mesma interação em um curto intervalo.
 *
 * @property string $id Identificador UUID.
 * @property string $tenant_id Identificador do tenant.
 * @property string $ticket_id Identificador do ticket vinculado.
 * @property string $rule_id Identificador da regra de auto reply vinculada.
 * @property \Illuminate\Support\Carbon $cooldown_until Data e hora de expiração do cooldown.
 * @property \Illuminate\Support\Carbon $created_at Data de registro.
 * @property \Illuminate\Support\Carbon $updated_at Data de atualização.
 *
 * @category Models
 */
class ChatAutoReplyCooldown extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $table = 'chat_auto_reply_cooldowns';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'ticket_id',
        'rule_id',
        'cooldown_until',
    ];

    protected $casts = [
        'cooldown_until' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\ChatAutoReplyCooldownFactory
    {
        return \Database\Factories\ChatAutoReplyCooldownFactory::new();
    }
}
