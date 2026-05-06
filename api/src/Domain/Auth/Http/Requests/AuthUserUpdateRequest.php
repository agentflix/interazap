<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Auth\Policies\AuthUserPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para atualização de usuário.
 */
final class AuthUserUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $user = $this->user('sanctum');

        if (! $user) {
            return;
        }

        $isPlatformAdmin = $user->isSuperAdmin() && empty($user->tenant_id);

        if (! $isPlatformAdmin) {
            $this->merge(['tenant_id' => $user->tenant_id]);

            return;
        }

        if (! $this->filled('tenant_id') && $this->filled('company_id')) {
            $this->merge(['tenant_id' => $this->input('company_id')]);

            return;
        }

        $userId = $this->route('id');

        if (! $this->filled('tenant_id') && $userId) {
            $model = AuthUser::find($userId);

            if ($model) {
                $this->merge(['tenant_id' => $model->tenant_id]);
            }
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user('sanctum');
        if (! $user) {
            return false;
        }

        $userId = $this->route('id');
        if (! $userId) {
            return false;
        }

        $model = AuthUser::find($userId);

        if (! $model) {
            return false;
        }

        $policy = app(AuthUserPolicy::class);

        if (! $policy->update($user, $model)) {
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['sometimes', 'uuid'],
            'company_id' => ['nullable', 'uuid', 'exists:platform_tenants,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('auth_users', 'email')
                    ->ignore($this->route('id'))
                    ->when(
                        $this->input('tenant_id'),
                        fn ($q) => $q->where('tenant_id', $this->input('tenant_id'))
                    ),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:100'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:auth_roles,name'],
            'is_active' => ['boolean'],
        ];
    }
}
