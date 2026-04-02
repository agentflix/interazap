<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Domain\Auth\Policies\AuthRolePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para criação de role.
 */
final class AuthRoleStoreRequest extends FormRequest
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

        $policy = app(AuthRolePolicy::class);

        return $policy->create($user);
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
                Rule::unique('auth_roles', 'name')->where('guard_name', 'sanctum'),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::exists('auth_permissions', 'name')->where('guard_name', 'sanctum')],
        ];
    }
}
