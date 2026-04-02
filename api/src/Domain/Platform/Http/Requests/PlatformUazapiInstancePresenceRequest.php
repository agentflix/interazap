<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para publicar status de presença da instância Uazapi.
 *
 * available: instância aparece como online
 * unavailable: instância aparece como offline
 */
final class PlatformUazapiInstancePresenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && (string) $this->user()->tenant_id !== '';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'presence' => ['required', 'string', 'in:available,unavailable'],
        ];
    }
}
