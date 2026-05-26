<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validação para atualização de configurações do tenant.
 *
 * Permite deep merge parcial — seções não enviadas são preservadas.
 */
final class UpdateTenantSettingsRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $tenantId = (string) $this->route('id');
        $tenant = PlatformTenant::query()->findOrFail($tenantId);

        return Gate::forUser($user)->check('updateSettings', $tenant);
    }

    /**
     * Regras de validação para atualização de configurações do tenant.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Localization
            'settings_localization' => ['sometimes', 'array'],
            'settings_localization.timezone' => ['sometimes', 'string', 'timezone'],
            'settings_localization.dateFormat' => [
                'sometimes',
                'string',
                Rule::in(['DD/MM/YYYY', 'MM/DD/YYYY', 'YYYY-MM-DD']),
            ],
            'settings_localization.timeFormat' => [
                'sometimes',
                'string',
                Rule::in(['12h', '24h']),
            ],
            'settings_localization.currencyFormat' => [
                'sometimes',
                'string',
                Rule::in(['BRL', 'USD', 'EUR']),
            ],

            // Privacy
            'settings_privacy' => ['sometimes', 'array'],
            'settings_privacy.presence' => [
                'sometimes',
                'string',
                Rule::in(['all', 'team', 'hidden']),
            ],
            'settings_privacy.readReceipt' => ['sometimes', 'boolean'],
            'settings_privacy.notificationPreview' => ['sometimes', 'boolean'],

            // Chat (Auto-close by inactivity)
            'settings_chat' => ['sometimes', 'array'],
            'settings_chat.auto_close_inactivity_enabled' => ['sometimes', 'boolean'],
            'settings_chat.auto_close_inactivity_minutes' => [
                'sometimes',
                'integer',
                Rule::in([5, 10, 15, 30, 45, 60, 120]),
            ],
            'settings_chat.auto_close_inactivity_target' => [
                'sometimes',
                'string',
                Rule::in(['both', 'client', 'agent']),
            ],
            'settings_chat.auto_close_inactivity_message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Prepara os dados antes da validação, convertendo strings 'true'/'false' em booleanos.
     */
    protected function prepareForValidation(): void
    {
        $this->replace(
            array_map(
                fn ($value) => is_string($value) && $value === 'true'
                    ? true
                    : (is_string($value) && $value === 'false' ? false : $value),
                $this->all()
            )
        );
    }
}
