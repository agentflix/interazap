<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para funil de negociação.
 */
class CRMFunnelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $stepsRule = $this->isMethod('post') ? ['required', 'array'] : ['sometimes', 'array'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'steps' => $stepsRule,
            'steps.*.name' => ['required_with:steps', 'string', 'max:255'],
            'steps.*.order' => ['required_with:steps', 'integer'],
            'steps.*.color' => ['required_with:steps', 'string'],
            'steps.*.is_active' => ['required_with:steps', 'boolean'],
        ];
    }
}
