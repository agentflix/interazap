<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Database\Factories\ChatTicketExtendedFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Dados estendidos de um Ticket de Atendimento.
 *
 * Armazena campos de baixa frequência de acesso (SLAs, auto-close, profile picture)
 * em tabela separada para otimizar consultas de listagem no chat_tickets core.
 *
 * @property string $id Identificador UUID.
 * @property string $ticket_id Identificador do ticket core.
 * @property string|null $subject Assunto do ticket.
 * @property string|null $profile_picture_url URL da foto de perfil do contato.
 * @property \Illuminate\Support\Carbon|null $human_takeover_at Data da tomada humana.
 * @property string|null $closed_by UUID do usuario que fechou.
 * @property string|null $close_reason Motivo do fechamento.
 * @property int $auto_close_queue_after_minutes Tempo para auto-fechar na fila.
 * @property int $auto_close_in_progress_after_minutes Tempo para auto-fechar em andamento.
 * @property \Illuminate\Support\Carbon|null $sla_first_response_due_at SLA de primeira resposta.
 * @property \Illuminate\Support\Carbon|null $sla_resolution_due_at SLA de resolucao.
 * @property bool $sla_first_response_breached Se SLA de primeira resposta foi violado.
 * @property bool $sla_resolution_breached Se SLA de resolucao foi violado.
 *
 * @category Models
 */
class ChatTicketExtended extends Model
{
    use HasFactory;

    protected $table = 'chat_tickets_extended';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'ticket_id',
        'subject',
        'profile_picture_url',
        'human_takeover_at',
        'closed_by',
        'close_reason',
        'auto_close_queue_after_minutes',
        'auto_close_in_progress_after_minutes',
        'sla_first_response_due_at',
        'sla_resolution_due_at',
        'sla_first_response_breached',
        'sla_resolution_breached',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'human_takeover_at' => 'datetime',
        'sla_first_response_due_at' => 'datetime',
        'sla_resolution_due_at' => 'datetime',
        'sla_first_response_breached' => 'bool',
        'sla_resolution_breached' => 'bool',
        'auto_close_queue_after_minutes' => 'integer',
        'auto_close_in_progress_after_minutes' => 'integer',
    ];

    /**
     * Inicializar o modelo e definir comportamentos automaticos.
     */
    protected static function booted(): void
    {
        static::creating(function (self $extended): void {
            if (! $extended->id) {
                $extended->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Relacionamento com o Ticket core.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ChatTicket::class, 'ticket_id');
    }

    protected static function newFactory(): ChatTicketExtendedFactory
    {
        return ChatTicketExtendedFactory::new();
    }
}
