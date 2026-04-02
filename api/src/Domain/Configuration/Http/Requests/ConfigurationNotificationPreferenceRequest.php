<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Requests;

use Domain\Configuration\Models\ConfigurationNotificationPreference;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualizar uma preferência de notificação.
 */
/**
 * FormRequest for notification preference updates.
 */
final class ConfigurationNotificationPreferenceRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channels' => 'required|array',
            'channels.*' => 'string|in:'.implode(',', array_keys(ConfigurationNotificationPreference::CHANNELS)),
            'enabled' => 'boolean',
            'quiet_start' => 'nullable|date_format:H:i',
            'quiet_end' => 'nullable|date_format:H:i',
        ];
    }
}
