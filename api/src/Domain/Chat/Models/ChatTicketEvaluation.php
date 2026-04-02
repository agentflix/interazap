<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Database\Factories\ChatTicketEvaluationFactory;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Modelo de Avaliação de Ticket (CSAT/NPS).
 *
 * Registra a satisfação do cliente após o encerramento de um ticket.
 * Utiliza um token único para permitir avaliações via link externo sem necessidade de login.
 *
 * @property string $id Identificador UUID.
 * @property string $tenant_id Identificador do tenant proprietário.
 * @property string $ticket_id Identificador do ticket avaliado.
 * @property string $token Chave única de acesso para a avaliação pública.
 * @property int|null $rating Nota atribuída pelo cliente (ex: 1 a 5).
 * @property string|null $comment Comentários ou feedback textual.
 * @property \Illuminate\Support\Carbon|null $submitted_at Data e hora em que a avaliação foi enviada.
 * @property \Illuminate\Support\Carbon $created_at Data de criação do registro de solicitação.
 * @property \Illuminate\Support\Carbon $updated_at Data de atualização.
 *
 * @category Models
 */
class ChatTicketEvaluation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'chat_ticket_evaluations';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'ticket_id',
        'token',
        'rating',
        'comment',
        'submitted_at',
    ];

    protected $casts = [
        'rating' => 'int',
        'submitted_at' => 'datetime',
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
     * Relacionamento com o ticket avaliado.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ChatTicket::class, 'ticket_id');
    }

    protected static function newFactory(): ChatTicketEvaluationFactory
    {
        return ChatTicketEvaluationFactory::new();
    }
}
