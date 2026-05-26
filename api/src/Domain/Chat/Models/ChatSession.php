<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Database\Factories\ChatSessionFactory;
use Domain\CRM\Models\CRMContact;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Modelo de Sessão de Webchat.
 *
 * Gerencia sessões anônimas ou autenticadas do webchat, rastreando a atividade
 * do visitante e vinculando-a a um contato e a um ticket de atendimento.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $contact_id
 * @property string $ticket_id
 * @property string $token
 * @property array|null $client_info
 * @property Carbon|null $last_activity_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ChatSession extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'chat_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'contact_id',
        'ticket_id',
        'token',
        'client_info',
        'last_activity_at',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'client_info' => 'array',
        'last_activity_at' => 'datetime',
    ];

    /**
     * Inicializa o modelo, gerando UUID e token automáticos na criação.
     */
    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            if (! $session->id) {
                $session->id = (string) Str::orderedUuid();
            }
            if (! $session->token) {
                $session->token = (string) Str::uuid();
            }
        });
    }

    /**
     * Relacionamento com o ticket de atendimento vinculado à sessão.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ChatTicket::class, 'ticket_id');
    }

    /**
     * Relacionamento com o contato CRM associado à sessão.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CRMContact::class, 'contact_id');
    }

    /**
     * Atualizar timestamp de última atividade.
     */
    public function touchLastActivity(): void
    {
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Criar nova instância da Factory para testes.
     */
    protected static function newFactory(): ChatSessionFactory
    {
        return ChatSessionFactory::new();
    }
}
