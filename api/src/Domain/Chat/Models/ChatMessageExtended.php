<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Database\Factories\ChatMessageExtendedFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Dados estendidos de uma Mensagem de Chat.
 *
 * Armazena campos de baixa frequência de acesso (arquivos de midia,
 * transcricao, reacoes, historico de edicao) em tabela separada
 * para otimizar consultas de listagem no chat_messages core.
 *
 * @property string $id Identificador UUID.
 * @property string $message_id Identificador da mensagem core.
 * @property string|null $file_url URL do arquivo de midia.
 * @property string|null $file_name Nome original do arquivo.
 * @property string|null $mime_type Tipo MIME do arquivo.
 * @property int|null $file_size Tamanho do arquivo em bytes.
 * @property string|null $media_transcription Texto da transcricao.
 * @property string|null $media_transcription_provider Provider utilizado.
 * @property string|null $media_transcription_status Status da transcricao.
 * @property int|null $media_transcription_tokens Tokens consumidos.
 * @property float|null $media_transcription_cost Custo da transcricao em USD.
 * @property \Illuminate\Support\Carbon|null $media_transcribed_at Data da transcricao.
 * @property array|null $reactions Lista de reacoes.
 * @property bool $is_edited Indica se a mensagem foi editada.
 * @property \Illuminate\Support\Carbon|null $edited_at Data da ultima edicao.
 * @property array|null $edit_history Historico de edicoes.
 * @property string|null $error_message Mensagem de erro.
 *
 * @category Models
 */
class ChatMessageExtended extends Model
{
    use HasFactory;

    protected $table = 'chat_messages_extended';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'message_id',
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
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'reactions' => 'array',
        'is_edited' => 'bool',
        'edited_at' => 'datetime',
        'edit_history' => 'array',
        'media_transcription_tokens' => 'integer',
        'media_transcription_cost' => 'float',
        'media_transcribed_at' => 'datetime',
    ];

    /**
     * Criar uma nova instância da Factory para testes.
     */
    protected static function newFactory(): ChatMessageExtendedFactory
    {
        return ChatMessageExtendedFactory::new();
    }

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
     * Relacionamento com a Mensagem core.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}
