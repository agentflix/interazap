<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Modelo de Transferência de Ticket.
 *
 * Rastreia o histórico de encaminhamento de conversas entre atendentes ou departamentos,
 * registrando o motivo e o status da transferência.
 *
 * @property string $id Identificador UUID.
 * @property string $ticket_id Identificador do ticket transferido.
 * @property string|null $from_user_id Usuário de origem (remetente).
 * @property string|null $to_user_id Usuário de destino (destinatário).
 * @property string|null $reason Motivo da transferência.
 * @property string $status Status do processo (pending, accepted, rejected, completed).
 * @property \Illuminate\Support\Carbon|null $transferred_at Data e hora da efetivação.
 * @property \Illuminate\Support\Carbon $created_at Data de solicitação.
 * @property \Illuminate\Support\Carbon $updated_at Data de atualização.
 *
 * @category Models
 */
class ChatTicketTransfer extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'chat_ticket_transfers';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'ticket_id',
        'from_user_id',
        'to_user_id',
        'reason',
        'status',
        'transferred_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $transfer): void {
            if (! $transfer->id) {
                $transfer->id = (string) Str::orderedUuid();
            }
        });
    }
}
