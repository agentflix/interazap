<?php

declare(strict_types=1);

namespace Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Lead interno da plataforma InteraZap (prospect da própria empresa).
 *
 * Esses leads NÃO pertencem a nenhum tenant cliente — representam
 * pessoas/empresas interessadas em contratar o produto InteraZap.
 * Capturados via landing pages, modais de saída e formulários públicos.
 *
 * @property string $id
 * @property string $name
 * @property string $phone
 * @property string $email
 * @property string|null $company
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $referrer
 * @property string|null $user_agent
 * @property string|null $ip_address
 * @property bool $lgpd_consent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class PlatformLead extends Model
{
    /**
     * Tabela associada ao modelo.
     */
    protected $table = 'platform_leads';

    /**
     * Chave primária não auto-incrementa (UUID).
     */
    public $incrementing = false;

    /**
     * Tipo da chave primária.
     */
    protected $keyType = 'string';

    /**
     * Atributos atribuíveis em massa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'phone',
        'email',
        'company',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'referrer',
        'user_agent',
        'ip_address',
        'lgpd_consent',
    ];

    /**
     * Casts de atributos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'lgpd_consent' => 'boolean',
    ];

    /**
     * Hooks do ciclo de vida do modelo.
     */
    protected static function booted(): void
    {
        self::creating(function (self $lead): void {
            if (! $lead->id) {
                $lead->id = (string) Str::orderedUuid();
            }
        });
    }
}
