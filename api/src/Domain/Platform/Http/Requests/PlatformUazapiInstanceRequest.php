<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criação de instância Uazapi.
 */
final class PlatformUazapiInstanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || (string) $user->tenant_id === '') {
            return false;
        }

        return $user->can('whatsapp.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'system_name' => ['nullable', 'string', 'max:100'],
            'config' => ['nullable', 'array'],
            'config.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
