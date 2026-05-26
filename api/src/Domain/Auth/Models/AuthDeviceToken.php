<?php

declare(strict_types=1);

namespace Domain\Auth\Models;

use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token de dispositivo para push notifications por usuário.
 */
final class AuthDeviceToken extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'auth_device_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'platform',
        'token',
        'device_name',
        'last_active_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** Retorna o usuário dono do token de dispositivo. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id');
    }
}
