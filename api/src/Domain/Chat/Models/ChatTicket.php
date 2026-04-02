<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Database\Factories\ChatTicketFactory;
use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\CRM\Models\CRMContact;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Modelo de Ticket de Atendimento.
 *
 * Representa um chamado de suporte ou conversa ativa entre a empresa
 * e um contato externo, gerenciando status, SLAs, filas e atribuição a agentes.
 *
 * @property string|null $subject Assunto do ticket (via extended).
 * @property string|null $profile_picture_url URL da foto de perfil (via extended).
 * @property Carbon|null $human_takeover_at Data da tomada humana (via extended).
 * @property string|null $closed_by UUID do usuario que fechou (via extended).
 * @property string|null $close_reason Motivo do fechamento (via extended).
 * @property int|null $auto_close_queue_after_minutes Tempo auto-fechar na fila (via extended).
 * @property int|null $auto_close_in_progress_after_minutes Tempo auto-fechar em andamento (via extended).
 * @property Carbon|null $sla_first_response_due_at SLA primeira resposta (via extended).
 * @property Carbon|null $sla_resolution_due_at SLA resolucao (via extended).
 * @property bool $sla_first_response_breached Se SLA primeira resposta violado (via extended).
 * @property bool $sla_resolution_breached Se SLA resolucao violado (via extended).
 *
 * @category Models
 */
class ChatTicket extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'chat_tickets';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'contact_id',
        'instance_id',
        'assigned_to',
        'protocol',
        'channel',
        'remote_jid',
        'phone',
        'phone_e164',
        'push_name',
        'status',
        'priority',
        'category',
        'is_group',
        'is_bot_active',
        'started_at',
        'first_response_at',
        'last_message_at',
        'closed_at',
        'closed_mode',
        'sentiment',
        'sentiment_score',
        'sentiment_updated_at',
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
        'tags',
        'metadata',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'is_group' => 'bool',
        'is_bot_active' => 'bool',
        'tags' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'first_response_at' => 'datetime',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
        'sentiment_score' => 'integer',
        'sentiment_updated_at' => 'datetime',
    ];

    /**
     * Inicializar o modelo e definir comportamentos automáticos.
     */
    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            if (! $ticket->id) {
                $ticket->id = (string) Str::orderedUuid();
            }
        });

        static::created(function (self $ticket): void {
            $ticket->flushPendingExtended();

            AutopilotTriggerFired::dispatch(
                (string) $ticket->tenant_id,
                AutopilotTriggerType::TICKET_CREATED,
                [
                    'ticket_id' => (string) $ticket->id,
                    'contact_id' => (string) ($ticket->contact_id ?? ''),
                    'channel' => (string) ($ticket->channel ?? ''),
                    'instance_id' => (string) ($ticket->instance_id ?? ''),
                    'source_type' => 'ticket',
                ],
                (string) $ticket->id,
            );
        });

        static::updated(function (self $ticket): void {
            $ticket->flushPendingExtended();
        });
    }

    /**
     * Criar uma nova instância da Factory para testes.
     */
    protected static function newFactory(): ChatTicketFactory
    {
        return ChatTicketFactory::new();
    }

    /**
     * Dados estendidos do ticket (SLAs, profile picture, auto-close, etc).
     */
    public function extended(): HasOne
    {
        return $this->hasOne(ChatTicketExtended::class, 'ticket_id');
    }

    /**
     * Relacionamento com as Mensagens do ticket.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'ticket_id');
    }

    /**
     * Relacionamento com a Instância de conexão (Canal).
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(ChatInstance::class, 'instance_id');
    }

    /**
     * Relacionamento com a Avaliação de atendimento (CSAT/NPS).
     */
    public function evaluation(): HasOne
    {
        return $this->hasOne(ChatTicketEvaluation::class, 'ticket_id');
    }

    /**
     * Relacionamento com o Usuário (Agente) responsável.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\Domain\Auth\Models\AuthUser::class, 'assigned_to');
    }

    /**
     * Relacionamento com o Contato do CRM.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CRMContact::class, 'contact_id');
    }

    /**
     * Obter apenas a mensagem mais recente trocada no ticket.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'ticket_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(1);
    }

    // ── Extended column proxies ───────────────────────────────────

    /**
     * Proxy: assunto do ticket (armazenado em chat_tickets_extended).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function subject(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->subject,
            set: fn (?string $value) => $this->setExtendedAttribute('subject', $value),
        );
    }

    /**
     * Proxy: URL da foto de perfil do contato (armazenado em chat_tickets_extended).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function profilePictureUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->profile_picture_url,
            set: fn (?string $value) => $this->setExtendedAttribute('profile_picture_url', $value),
        );
    }

    /**
     * Proxy: data da tomada humana (armazenado em chat_tickets_extended).
     *
     * @return Attribute<Carbon|null, Carbon|string|null>
     */
    protected function humanTakeoverAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->human_takeover_at,
            set: fn (Carbon|string|null $value) => $this->setExtendedAttribute('human_takeover_at', $value),
        );
    }

    /**
     * Proxy: UUID do usuario que fechou o ticket (armazenado em chat_tickets_extended).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function closedBy(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->closed_by,
            set: fn (?string $value) => $this->setExtendedAttribute('closed_by', $value),
        );
    }

    /**
     * Proxy: motivo do fechamento (armazenado em chat_tickets_extended).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function closeReason(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->close_reason,
            set: fn (?string $value) => $this->setExtendedAttribute('close_reason', $value),
        );
    }

    /**
     * Proxy: tempo para auto-fechar na fila (armazenado em chat_tickets_extended).
     *
     * @return Attribute<int|null, int|null>
     */
    protected function autoCloseQueueAfterMinutes(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->auto_close_queue_after_minutes,
            set: fn (?int $value) => $this->setExtendedAttribute('auto_close_queue_after_minutes', $value),
        );
    }

    /**
     * Proxy: tempo para auto-fechar em andamento (armazenado em chat_tickets_extended).
     *
     * @return Attribute<int|null, int|null>
     */
    protected function autoCloseInProgressAfterMinutes(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->auto_close_in_progress_after_minutes,
            set: fn (?int $value) => $this->setExtendedAttribute('auto_close_in_progress_after_minutes', $value),
        );
    }

    /**
     * Proxy: SLA de primeira resposta (armazenado em chat_tickets_extended).
     *
     * @return Attribute<Carbon|null, Carbon|string|null>
     */
    protected function slaFirstResponseDueAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->sla_first_response_due_at,
            set: fn (Carbon|string|null $value) => $this->setExtendedAttribute('sla_first_response_due_at', $value),
        );
    }

    /**
     * Proxy: SLA de resolucao (armazenado em chat_tickets_extended).
     *
     * @return Attribute<Carbon|null, Carbon|string|null>
     */
    protected function slaResolutionDueAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->sla_resolution_due_at,
            set: fn (Carbon|string|null $value) => $this->setExtendedAttribute('sla_resolution_due_at', $value),
        );
    }

    /**
     * Proxy: se SLA de primeira resposta foi violado (armazenado em chat_tickets_extended).
     *
     * @return Attribute<bool, bool>
     */
    protected function slaFirstResponseBreached(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended->sla_first_response_breached ?? false,
            set: fn (bool $value) => $this->setExtendedAttribute('sla_first_response_breached', $value),
        );
    }

    /**
     * Proxy: se SLA de resolucao foi violado (armazenado em chat_tickets_extended).
     *
     * @return Attribute<bool, bool>
     */
    protected function slaResolutionBreached(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended->sla_resolution_breached ?? false,
            set: fn (bool $value) => $this->setExtendedAttribute('sla_resolution_breached', $value),
        );
    }

    /**
     * Gravar um atributo na tabela extended, criando o registro se necessario.
     *
     * @return array<string, mixed> Array vazio para evitar escrita na tabela core.
     */
    private function setExtendedAttribute(string $key, mixed $value): array
    {
        $this->pendingExtendedAttributes[$key] = $value;

        return [];
    }

    /**
     * Atributos pendentes para a tabela extended (usados antes do save).
     *
     * @var array<string, mixed>
     */
    private array $pendingExtendedAttributes = [];

    /**
     * Gravar atributos pendentes na tabela extended apos criacao/atualizacao do ticket.
     */
    public function flushPendingExtended(): void
    {
        if ($this->pendingExtendedAttributes === []) {
            return;
        }

        if (! $this->id) {
            return;
        }

        $extended = $this->extended()->firstOrCreate([
            'ticket_id' => $this->id,
        ], [
            'id' => (string) Str::orderedUuid(),
        ]);

        foreach ($this->pendingExtendedAttributes as $key => $value) {
            $extended->{$key} = $value;
        }

        $extended->save();
        $this->setRelation('extended', $extended);
        $this->pendingExtendedAttributes = [];
    }
}
