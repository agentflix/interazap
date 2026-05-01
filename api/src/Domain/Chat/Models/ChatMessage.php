<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Database\Factories\ChatMessageFactory;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Modelo de Mensagem de Chat.
 *
 * Armazena o conteúdo core de uma conversação vinculada a um ticket.
 * Campos de midia, transcricao, reacoes e edicao ficam em ChatMessageExtended.
 *
 * @property string $id Identificador UUID.
 * @property string $tenant_id Identificador do tenant proprietário.
 * @property string $ticket_id Identificador do ticket vinculado.
 * @property string|null $user_id Identificador do atendente que enviou a mensagem.
 * @property string|null $contact_id Identificador do contato associado à mensagem.
 * @property string|null $content Conteúdo textual da mensagem.
 * @property string $type Tipo da mensagem (text, image, audio, file, etc).
 * @property string $direction Direção da mensagem (inbound ou outbound).
 * @property bool $is_from_contact Indica se a mensagem foi originada pelo cliente.
 * @property string $status Estado atual (queued, sent, delivered, read, failed).
 * @property string|null $external_id Identificador da mensagem no provedor externo.
 * @property array $metadata Atributos técnicos adicionais da mensagem.
 * @property \Illuminate\Support\Carbon|null $sent_at Data em que foi enviada.
 * @property \Illuminate\Support\Carbon|null $delivered_at Data em que foi entregue ao destinatário.
 * @property \Illuminate\Support\Carbon|null $read_at Data em que foi lida pelo destinatário.
 * @property bool $is_deleted Indica se a mensagem foi removida logicamente.
 * @property \Illuminate\Support\Carbon|null $deleted_at Data da remoção.
 * @property string|null $deleted_by Identificador de quem realizou a exclusão.
 * @property string|null $file_url URL do arquivo de midia (via extended).
 * @property string|null $file_name Nome original do arquivo (via extended).
 * @property string|null $mime_type Tipo MIME do arquivo (via extended).
 * @property int|null $file_size Tamanho do arquivo em bytes (via extended).
 * @property string|null $error_message Mensagem de erro (via extended).
 * @property array|null $reactions Lista de reacoes (via extended).
 * @property bool $is_edited Indica se a mensagem foi editada (via extended).
 * @property Carbon|null $edited_at Data da ultima edicao (via extended).
 * @property array|null $edit_history Historico de edicoes (via extended).
 * @property string|null $media_transcription Texto da transcricao (via extended).
 * @property string|null $media_transcription_provider Provider de transcricao (via extended).
 * @property string|null $media_transcription_status Status da transcricao (via extended).
 * @property int|null $media_transcription_tokens Tokens consumidos (via extended).
 * @property float|null $media_transcription_cost Custo em USD (via extended).
 * @property Carbon|null $media_transcribed_at Data da transcricao (via extended).
 *
 * @category Models
 */
class ChatMessage extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'chat_messages';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Accessors included automatically in toArray() / JSON serialization.
     *
     * @var list<string>
     */
    protected $appends = [
        'file_url',
        'file_name',
        'mime_type',
        'file_size',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'ticket_id',
        'user_id',
        'contact_id',
        'content',
        'type',
        'direction',
        'is_from_contact',
        'source',
        'status',
        'transcription',
        'audio_duration_ms',
        'audio_mime_type',
        'external_id',
        'metadata',
        'file_url',
        'file_name',
        'mime_type',
        'file_size',
        'media_transcription',
        'media_transcription_provider',
        'media_transcription_status',
        'media_transcription_tokens',
        'media_transcription_cost',
        'media_transcribed_at',
        'reactions',
        'is_edited',
        'edited_at',
        'edit_history',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'is_deleted',
        'deleted_at',
        'deleted_by',
    ];

    protected $casts = [
        'is_from_contact' => 'bool',
        'metadata' => 'array',
        'is_deleted' => 'bool',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'deleted_at' => 'datetime',
        'audio_duration_ms' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            if (! $message->id) {
                $message->id = (string) Str::orderedUuid();
            }
        });

        static::created(function (self $message): void {
            $message->flushPendingExtended();
        });
    }

    /**
     * Criar uma nova instância da Factory para testes.
     */
    protected static function newFactory(): ChatMessageFactory
    {
        return ChatMessageFactory::new();
    }

    /**
     * Dados estendidos da mensagem (arquivos, transcricao, reacoes, edicao).
     */
    public function extended(): HasOne
    {
        return $this->hasOne(ChatMessageExtended::class, 'message_id');
    }

    /**
     * Relacionamento com o Ticket de Chat.
     *
     * @return BelongsTo Conexão com o modelo ChatTicket.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ChatTicket::class, 'ticket_id');
    }

    /**
     * Relacionamento com o Usuário (Atendente).
     *
     * @return BelongsTo Conexão com o modelo AuthUser.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id');
    }

    // ── Extended column proxies ───────────────────────────────────

    /**
     * Proxy: URL do arquivo de midia (armazenado em chat_messages_extended).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function fileUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->file_url,
            set: fn (?string $value) => $this->setExtendedAttribute('file_url', $value),
        );
    }

    /**
     * Proxy: nome original do arquivo (armazenado em chat_messages_extended).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function fileName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->file_name,
            set: fn (?string $value) => $this->setExtendedAttribute('file_name', $value),
        );
    }

    /**
     * Proxy: tipo MIME do arquivo (armazenado em chat_messages_extended).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function mimeType(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->mime_type,
            set: fn (?string $value) => $this->setExtendedAttribute('mime_type', $value),
        );
    }

    /**
     * Proxy: tamanho do arquivo em bytes (armazenado em chat_messages_extended).
     *
     * @return Attribute<int|null, int|null>
     */
    protected function fileSize(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->file_size,
            set: fn (?int $value) => $this->setExtendedAttribute('file_size', $value),
        );
    }

    /**
     * Proxy: mensagem de erro (armazenado em chat_messages_extended).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function errorMessage(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->error_message,
            set: fn (?string $value) => $this->setExtendedAttribute('error_message', $value),
        );
    }

    /**
     * Proxy: lista de reacoes (armazenado em chat_messages_extended).
     *
     * @return Attribute<array<mixed>|null, array<mixed>|null>
     */
    protected function reactions(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->reactions,
            set: fn (?array $value) => $this->setExtendedAttribute('reactions', $value),
        );
    }

    /**
     * Proxy: indica se a mensagem foi editada (armazenado em chat_messages_extended).
     *
     * @return Attribute<bool, bool>
     */
    protected function isEdited(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended->is_edited ?? false,
            set: fn (bool $value) => $this->setExtendedAttribute('is_edited', $value),
        );
    }

    /**
     * Proxy: data da ultima edicao (armazenado em chat_messages_extended).
     *
     * @return Attribute<Carbon|null, Carbon|string|null>
     */
    protected function editedAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->edited_at,
            set: fn (Carbon|string|null $value) => $this->setExtendedAttribute('edited_at', $value),
        );
    }

    /**
     * Proxy: historico de edicoes (armazenado em chat_messages_extended).
     *
     * @return Attribute<array<mixed>|null, array<mixed>|null>
     */
    protected function editHistory(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->edit_history,
            set: fn (?array $value) => $this->setExtendedAttribute('edit_history', $value),
        );
    }

    /**
     * Proxy: texto da transcricao de midia (armazenado em chat_messages_extended).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function mediaTranscription(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->media_transcription,
            set: fn (?string $value) => $this->setExtendedAttribute('media_transcription', $value),
        );
    }

    /**
     * Proxy: provider de transcricao (armazenado em chat_messages_extended).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function mediaTranscriptionProvider(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->media_transcription_provider,
            set: fn (?string $value) => $this->setExtendedAttribute('media_transcription_provider', $value),
        );
    }

    /**
     * Proxy: status da transcricao (armazenado em chat_messages_extended).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function mediaTranscriptionStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->media_transcription_status,
            set: fn (?string $value) => $this->setExtendedAttribute('media_transcription_status', $value),
        );
    }

    /**
     * Proxy: tokens consumidos na transcricao (armazenado em chat_messages_extended).
     *
     * @return Attribute<int|null, int|null>
     */
    protected function mediaTranscriptionTokens(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->media_transcription_tokens,
            set: fn (?int $value) => $this->setExtendedAttribute('media_transcription_tokens', $value),
        );
    }

    /**
     * Proxy: custo da transcricao em USD (armazenado em chat_messages_extended).
     *
     * @return Attribute<float|null, float|null>
     */
    protected function mediaTranscriptionCost(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->media_transcription_cost,
            set: fn (?float $value) => $this->setExtendedAttribute('media_transcription_cost', $value),
        );
    }

    /**
     * Proxy: data da transcricao (armazenado em chat_messages_extended).
     *
     * @return Attribute<Carbon|null, Carbon|string|null>
     */
    protected function mediaTranscribedAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->extended?->media_transcribed_at,
            set: fn (Carbon|string|null $value) => $this->setExtendedAttribute('media_transcribed_at', $value),
        );
    }

    /**
     * Gravar um atributo na tabela extended, criando o registro se necessario.
     *
     * @return array<string, mixed> Array vazio para evitar escrita na tabela core.
     */
    private function setExtendedAttribute(string $key, mixed $value): array
    {
        if (! $this->exists) {
            $this->pendingExtendedAttributes[$key] = $value;

            return [];
        }

        $extended = $this->extended ?? $this->extended()->create([
            'id' => (string) Str::orderedUuid(),
            'message_id' => $this->id,
        ]);
        $extended->{$key} = $value;
        $extended->save();
        $this->setRelation('extended', $extended);

        return [];
    }

    /**
     * Atributos pendentes para a tabela extended (usados antes do save).
     *
     * @var array<string, mixed>
     */
    private array $pendingExtendedAttributes = [];

    /**
     * Gravar atributos pendentes na tabela extended apos criacao da mensagem.
     */
    public function flushPendingExtended(): void
    {
        if ($this->pendingExtendedAttributes === []) {
            return;
        }

        $extended = $this->extended ?? $this->extended()->create([
            'id' => (string) Str::orderedUuid(),
            'message_id' => $this->id,
        ]);

        foreach ($this->pendingExtendedAttributes as $key => $value) {
            $extended->{$key} = $value;
        }

        $extended->save();
        $this->setRelation('extended', $extended);
        $this->pendingExtendedAttributes = [];
    }

    /**
     * Scope: Filtrar por hash md5 do conteudo.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWhereContentHash(Builder $query, string $hash): void
    {
        $query->whereRaw('MD5(LOWER(TRIM(content))) = ?', [$hash]);
    }
}
