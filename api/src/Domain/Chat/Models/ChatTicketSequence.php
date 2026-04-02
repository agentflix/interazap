<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Modelo de Sequenciador de Protocolos.
 *
 * Responsável por gerar números de protocolo únicos e sequenciais para novos tickets,
 * respeitando o isolamento por tenant e permitindo prefixos customizáveis.
 *
 * @property string $id Identificador UUID.
 * @property string $tenant_id Identificador do tenant.
 * @property int $current_value Valor numérico atual da sequência.
 * @property string $prefix Prefixo textual do protocolo (ex: ATEND-).
 * @property \Illuminate\Support\Carbon $created_at Data de criação.
 * @property \Illuminate\Support\Carbon $updated_at Data de atualização.
 *
 * @category Models
 */
class ChatTicketSequence extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'chat_ticket_sequences';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'current_value',
        'prefix',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $seq): void {
            if (! $seq->id) {
                $seq->id = (string) Str::orderedUuid();
            }
        });
    }

    /**
     * Incrementar e obter o próximo número de protocolo para o tenant.
     *
     * Utiliza lock de banco de dados para evitar duplicidade em condições de corrida.
     *
     * Formato retornado: TK-YYYYMMDDHHMMSS-000000.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $prefix  Prefixo a ser aplicado ao protocolo.
     * @return string Protocolo completo (ex: TK-20260303161045-000123).
     */
    public static function next(string $tenantId, string $prefix = ''): string
    {
        return DB::transaction(function () use ($tenantId, $prefix): string {
            $sequence = self::query()->lockForUpdate()->firstOrCreate(
                ['tenant_id' => $tenantId],
                ['current_value' => 0, 'prefix' => $prefix]
            );

            $sequence->current_value++;
            $sequence->prefix = $prefix;
            $sequence->save();

            $timestamp = now()->format('YmdHis');
            $sequenceValue = str_pad((string) $sequence->current_value, 6, '0', STR_PAD_LEFT);

            return sprintf('%s%s-%s', $sequence->prefix, $timestamp, $sequenceValue);
        });
    }
}
