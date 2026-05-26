<?php

declare(strict_types=1);

namespace Domain\Ai\Models;

use Domain\Chat\Models\ChatTicket;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Resumo de conversa gerado por IA.
 *
 * Armazena o resultado da sumarização de um ticket gerado pelo agente,
 * incluindo a contagem de mensagens processadas e o timestamp de geração.
 * Atualizado após cada run concluído via AiConversationSummaryListener.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $ticket_id
 * @property string $summary Texto do resumo gerado pela IA.
 * @property int $message_count Número de mensagens consideradas no resumo.
 * @property \Illuminate\Support\Carbon|null $generated_at Data/hora da geração.
 */
class AiConversationSummary extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'ai_conversation_summaries';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'ticket_id',
        'summary',
        'message_count',
        'generated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'message_count' => 'integer',
        'generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->id) {
                $item->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Ticket ao qual este resumo pertence.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ChatTicket::class, 'ticket_id');
    }
}
