<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Modelo de Resposta Rápida.
 *
 * Armazena fragmentos de texto pré-configurados que auxiliam os atendentes
 * a responder perguntas frequentes com agilidade via atalhos (shortcuts).
 *
 * @property string $id Identificador UUID.
 * @property string $tenant_id Identificador do tenant proprietário.
 * @property string $name Nome identificador da resposta.
 * @property string $shortcut Comando de atalho (ex: /ola).
 * @property string $content Texto completo da resposta.
 * @property string|null $category Categoria para organização.
 * @property bool $is_active Indica se a resposta está disponível.
 * @property \Illuminate\Support\Carbon $created_at Data de criação.
 * @property \Illuminate\Support\Carbon $updated_at Data de atualização.
 * @property \Illuminate\Support\Carbon|null $deleted_at Data da exclusão lógica.
 *
 * @category Models
 */
class ChatQuickAnswer extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'chat_quick_answers';

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

    protected static function newFactory(): \Database\Factories\ChatQuickAnswerFactory
    {
        return \Database\Factories\ChatQuickAnswerFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $qa): void {
            if (! $qa->id) {
                $qa->id = (string) Str::orderedUuid();
            }
        });
    }
}
