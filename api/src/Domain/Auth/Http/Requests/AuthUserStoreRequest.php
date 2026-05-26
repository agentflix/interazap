<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Policies\AuthUserPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para criação de usuário.
 */
final class AuthUserStoreRequest extends FormRequest
{
    /**
     * Prepara os dados antes da validação, injetando tenant_id e formatando o telefone.
     *
     * Super admins na rota platform/users podem informar tenant_id explicitamente;
     * usuários comuns têm o tenant_id fixado ao seu próprio tenant.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user('sanctum');

        if (! $user) {
            return;
        }

        $isPlatformAdmin = $user->isSuperAdmin()
            && ($this->isPlatformUsersRoute() || empty($user->tenant_id));

        if (! $isPlatformAdmin) {
            // Every tenant-scoped user can create only within their own tenant.
            $this->merge(['tenant_id' => $user->tenant_id]);

            return;
        }

        if (! $this->filled('tenant_id') && $this->filled('company_id')) {
            $this->merge(['tenant_id' => $this->input('company_id')]);
        }

        if ($phone = $this->input('phone')) {
            $digits = preg_replace('/\D/', '', (string) $phone);
            $formatted = match (strlen($digits)) {
                11 => sprintf('(%s)%s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7)),
                10 => sprintf('(%s)%s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6)),
                default => $phone,
            };
            $this->merge(['phone' => $formatted]);
        }
    }

    /** Verifica se a requisição é proveniente de uma rota do módulo platform/users. */
    private function isPlatformUsersRoute(): bool
    {
        $uri = (string) ($this->route()?->uri() ?? '');
        $path = trim($this->path(), '/');

        return str_contains($uri, 'platform/users')
            || str_contains($path, 'platform/users');
    }

    /**
     * Autoriza a criação verificando permissão via policy e impedindo atribuição de super-admin.
     *
     * @throws AuthorizationException Se tentar atribuir o perfil Administrador sem ser super-admin.
     */
    public function authorize(): bool
    {
        $user = $this->user('sanctum');
        if (! $user) {
            return false;
        }

        $policy = app(AuthUserPolicy::class);

        if (! $policy->create($user)) {
            return false;
        }

        // Security: prevent non-super-admin from assigning super-admin role.
        if (! $user->isSuperAdmin()) {
            $rolesToAssign = $this->input('roles', []) ?: ($this->input('role') ? [$this->input('role')] : []);
            if (in_array(AuthRole::ADMINISTRADOR_NAME, $rolesToAssign, true)) {
                throw new AuthorizationException('Não é permitido atribuir o perfil super-admin.');
            }
        }

        return true;
    }

    /**
     * Regras de validação para criação de usuário.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:platform_tenants,id'],
            'company_id' => ['nullable', 'uuid', 'exists:platform_tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('auth_users', 'email')->where(fn ($q) => $q->where('tenant_id', $this->input('tenant_id'))),
            ],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'celular_com_ddd'],
            'role' => ['nullable', 'string', 'max:100'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:auth_roles,name'],
            'is_active' => ['boolean'],
            'force_password_change' => ['nullable', 'boolean'],
        ];
    }
}
