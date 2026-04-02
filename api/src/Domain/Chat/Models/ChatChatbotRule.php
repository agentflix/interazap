<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Modelo de Regra de Chatbot.
 *
 * Define gatilhos (triggers) baseados em texto para respostas automáticas simples.
 * Permite configurar o tempo de "cooldown" para evitar repetições excessivas.
 *
 * @property string $id Identificador UUID.
 * @property string $tenant_id Identificador do tenant proprietário.
 * @property string $name Nome amigável da regra.
 * @property string $trigger_text Palavra-chave ou frase que dispara a regra.
 * @property string $response_text Conteúdo da resposta automática.
 * @property bool $is_active Indica se a regra está habilitada.
 * @property bool $is_welcome Indica se esta é a regra de boas-vindas (enviada automaticamente na primeira interação).
 * @property int $cooldown_seconds Tempo de espera em segundos antes de disparar novamente para o mesmo contato.
 * @property \Illuminate\Support\Carbon $created_at Data de criação.
 * @property \Illuminate\Support\Carbon $updated_at Data de atualização.
 *
 * @category Models
 */
class ChatChatbotRule extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'chat_chatbot_rules';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'trigger_text',
        'response_text',
        'is_active',
        'is_welcome',
        'cooldown_seconds',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'is_welcome' => 'bool',
        'cooldown_seconds' => 'int',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->id) {
                $item->id = (string) Str::orderedUuid();
            }
        });
    }

    protected static function newFactory(): \Database\Factories\ChatChatbotRuleFactory
    {
        return \Database\Factories\ChatChatbotRuleFactory::new();
    }
}
