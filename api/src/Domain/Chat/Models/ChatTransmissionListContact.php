<?php

declare(strict_types=1);

namespace Domain\Chat\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Domain\Shared\Scopes\TenantScope;
use Domain\Shared\Support\TenantContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Modelo de vínculo entre Lista de Transmissão e Contato.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $transmission_list_id
 * @property string $contact_id
 * @property string $status (pending, sent, failed)
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property string|null $error
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read ChatTransmissionList $transmissionList
 * @property-read \Domain\CRM\Models\CRMContact $contact
 */
final class ChatTransmissionListContact extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $table = 'chat_transmission_list_contacts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'transmission_list_id',
        'contact_id',
        'status',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $item): void {
            if (! $item->id) {
                $item->id = (string) Str::orderedUuid();
            }

            if (! $item->tenant_id) {
                $tenantId = TenantContext::get();

                if (! $tenantId && $item->transmission_list_id) {
                    $transmissionList = ChatTransmissionList::withoutGlobalScope(TenantScope::class)
                        ->find($item->transmission_list_id);
                    $tenantId = $transmissionList?->tenant_id;
                }

                if ($tenantId) {
                    $item->tenant_id = $tenantId;
                }
            }
        });
    }

    /**
     * Relacionamento com a lista de transmissão pai.
     */
    public function transmissionList(): BelongsTo
    {
        return $this->belongsTo(ChatTransmissionList::class, 'transmission_list_id');
    }

    /**
     * Relacionamento com o contato CRM vinculado.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(\Domain\CRM\Models\CRMContact::class, 'contact_id');
    }
}
