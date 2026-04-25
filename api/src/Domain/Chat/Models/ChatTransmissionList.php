<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Modelo de Lista de Transmissão de Chat.
 *
 * @property string $id Identificador UUID.
 * @property string $tenant_id Identificador do tenant proprietário.
 * @property string $name Nome descritivo da lista.
 * @property string|null $message Conteúdo da mensagem.
 * @property array|null $filter_criteria Critérios de filtro para seleção de contatos.
 * @property string|null $instance_id ID da instância WhatsApp.
 * @property string $status Status atual (draft, scheduled, running, completed, cancelled).
 * @property \Illuminate\Support\Carbon|null $scheduled_at Data e hora agendada para o início.
 * @property array $metadata Dados configuráveis e estatísticas da lista.
 * @property \Illuminate\Support\Carbon $created_at Data de criação.
 * @property \Illuminate\Support\Carbon $updated_at Data de atualização.
 * @property-read \Illuminate\Database\Eloquent\Collection|\Domain\Chat\Models\ChatTransmissionListContact[] $contacts
 *
 * @category Models
 */
class ChatTransmissionList extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'chat_transmission_lists';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'instance_id',
        'name',
        'message',
        'filter_criteria',
        'status',
        'scheduled_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'filter_criteria' => 'array',
        'scheduled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->id) {
                $item->id = (string) Str::orderedUuid();
            }
        });
    }

    public function contacts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ChatTransmissionListContact::class, 'transmission_list_id');
    }

    protected static function newFactory(): \Database\Factories\ChatTransmissionListFactory
    {
        return \Database\Factories\ChatTransmissionListFactory::new();
    }
}
