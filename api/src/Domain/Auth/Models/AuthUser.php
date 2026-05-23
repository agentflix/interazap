<?php

declare(strict_types=1);

namespace Domain\Auth\Models;

use Database\Factories\AuthUserFactory;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modelo de Usuário Autenticado.
 *
 * Representa a entidade central de acesso ao sistema, suportando
 * multi-tenancy, controle de acesso baseado em papéis (RBAC),
 * auditoria de alterações e autenticação via Sanctum.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $email
 * @property bool $force_password_change
 * @property PlatformTenant|null $tenant
 *
 * @category Models
 */
class AuthUser extends Authenticatable implements AuditableContract
{
    use Auditable;
    use BelongsToTenant;
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'auth_users';

    protected string $guard_name = 'sanctum';

    public $incrementing = false;

    protected $keyType = 'string';

    public function isSuperAdmin(): bool
    {
        return $this->hasRoleId(AuthRole::ADMINISTRADOR_ID);
    }

    /**
     * Verifica se o usuário possui uma role específica pelo UUID.
     */
    public function hasRoleId(string $roleId): bool
    {
        return $this->roles()->where('id', $roleId)->exists();
    }

    /**
     * Verifica se o usuário é o dono/administrador do tenant (tenant owner).
     */
    public function isInquilino(): bool
    {
        return $this->hasRoleId(AuthRole::INQUILINO_ID);
    }

    /**
     * Verifica se o usuário é gerente do tenant.
     */
    public function isManager(): bool
    {
        return $this->hasRoleId(AuthRole::GERENTE_ID);
    }

    /**
     * Verifica se o usuário é atendente do tenant.
     */
    public function isAgent(): bool
    {
        return $this->hasRoleId(AuthRole::ATENDENTE_ID);
    }

    /**
     * Verifica se o usuário é administrador de qualquer nível (platform ou tenant).
     */
    public function isAnyAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->isInquilino();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'email',
        'password',
        'phone',
        'avatar_url',
        'is_active',
        'force_password_change',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'email_verified_at',
        'preferences',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Definir conversão de tipos para atributos.
     *
     * @return array<string, string> Mapa de casting de atributos.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'force_password_change' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'preferences' => 'array',
        ];
    }

    /**
     * Obter o Tenant associado ao usuário.
     *
     * @return BelongsTo Relacionamento com a entidade PlatformTenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PlatformTenant::class, 'tenant_id');
    }

    /**
     * Tokens de dispositivos registrados para push notifications.
     *
     * @return HasMany<AuthDeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(AuthDeviceToken::class, 'user_id');
    }

    /**
     * @return array<int, string>
     */
    public function routeNotificationForApn(object $notification): array
    {
        return $this->activeDeviceTokensByPlatform('ios');
    }

    /**
     * @return array<int, string>
     */
    public function routeNotificationForFcm(object $notification): array
    {
        return $this->activeDeviceTokensByPlatform('android');
    }

    /**
     * Criar uma nova instância da Factory para testes.
     *
     * @return AuthUserFactory Instância configurada da fábrica.
     */
    protected static function newFactory(): AuthUserFactory
    {
        return AuthUserFactory::new();
    }

    /**
     * Override default permission check to honor scoped personal access tokens.
     */
    /**
     * @param  string|iterable<string>  $abilities
     * @param  mixed  $arguments
     */
    public function can($abilities, $arguments = []): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (is_string($abilities) && $this->tokenAllows($abilities)) {
            return true;
        }

        return parent::can($abilities, $arguments);
    }

    private function tokenAllows(string $permission): bool
    {
        $token = $this->currentAccessToken();

        if ($token === null) {
            return false;
        }

        return $this->tokenCan($permission) || $this->tokenCan('*');
    }

    /**
     * @return array<int, string>
     */
    private function activeDeviceTokensByPlatform(string $platform): array
    {
        $tokens = $this->relationLoaded('deviceTokens')
            ? $this->deviceTokens
            : $this->deviceTokens()->whereNull('revoked_at')->get();

        return $tokens
            ->where('platform', $platform)
            ->whereNull('revoked_at')
            ->pluck('token')
            ->filter(static fn (mixed $token): bool => is_string($token) && $token !== '')
            ->unique()
            ->values()
            ->all();
    }
}
