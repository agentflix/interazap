<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Modelo de Template de Mensagem.
 *
 * Define modelos estruturados de mensagens que podem ser utilizados
 * para disparos ativos ou respostas padronizadas. Diferencia-se das Quick Answers
 * por seguir, opcionalmente, o formato oficial de HSM (High Structured Messages).
 *
 * @property string $id Identificador UUID.
 * @property string $tenant_id Identificador do tenant proprietário.
 * @property string $name Nome do template.
 * @property string $shortcut Atalho de teclado para acesso rápido.
 * @property string $content Conteúdo textual com suporte a variáveis {{1}}.
 * @property string|null $category Categoria organizacional.
 * @property bool $is_active Indica se o template está apto para uso.
 * @property \Illuminate\Support\Carbon $created_at Data de criação.
 * @property \Illuminate\Support\Carbon $updated_at Data de atualização.
 * @property \Illuminate\Support\Carbon|null $deleted_at Data da exclusão lógica.
 *
 * @category Models
 */
class ChatMessageTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'chat_message_templates';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'shortcut',
        'content',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            if (! $template->id) {
                $template->id = (string) Str::orderedUuid();
            }
        });
    }

    protected static function newFactory(): \Database\Factories\ChatMessageTemplateFactory
    {
        return \Database\Factories\ChatMessageTemplateFactory::new();
    }
}
