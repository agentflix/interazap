<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Policies\AuthRolePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para atualização de role.
 */
final class AuthRoleUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user('sanctum');

        if (! $user) {
            return false;
        }

        $roleId = $this->route('id');

        if (! $roleId) {
            return false;
        }

        $role = AuthRole::find($roleId);

        if (! $role) {
            return false;
        }

        $policy = app(AuthRolePolicy::class);

        return $policy->update($user, $role);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('auth_roles', 'name')
                    ->where('guard_name', 'sanctum')
                    ->ignore($this->route('id')),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::exists('auth_permissions', 'name')->where('guard_name', 'sanctum')],
        ];
    }
}
