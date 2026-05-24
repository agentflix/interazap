<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Domain\Platform\Models\PlatformLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validação para conversão administrativa de lead da plataforma em tenant.
 */
final class PlatformLeadConvertRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->check('create', PlatformLead::class);
    }

    protected function prepareForValidation(): void
    {
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'celular_com_ddd'],
            'document' => ['nullable', 'cpf_ou_cnpj'],
            'plan_id' => ['nullable', 'uuid', Rule::exists('platform_plans', 'id')],
        ];
    }
}
