<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validacao para criacao de tenant da plataforma.
 */
final class PlatformTenantStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->check('create', PlatformTenant::class);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('state')) {
            $this->merge([
                'state' => strtoupper((string) $this->input('state')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tenant_code' => ['nullable', 'string', 'max:12', Rule::unique('platform_tenants', 'tenant_code')],
            'email' => ['nullable', 'email', 'max:255'],
            'document' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'segment_id' => ['required', 'uuid', 'exists:ai_prompt_segments,id'],
            'plan_id' => ['required', 'uuid', 'exists:platform_plans,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2', Rule::in(['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'])],
            'zip' => ['nullable', 'string', 'max:20'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'zipcode' => ['nullable', 'string', 'max:20'],
        ];
    }
}
