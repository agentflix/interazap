<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Requests;

use Domain\Configuration\Models\ConfigurationNotificationPreference;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualização em lote de preferências.
 */
/**
 * FormRequest for bulk notification preference updates.
 */
final class ConfigurationNotificationPreferenceBulkRequest extends FormRequest
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
            'preferences' => 'required|array',
            'preferences.*.type' => 'required|string',
            'preferences.*.channels' => 'required|array',
            'preferences.*.channels.*' => 'string|in:'.implode(',', array_keys(ConfigurationNotificationPreference::CHANNELS)),
            'preferences.*.enabled' => 'boolean',
            'preferences.*.quiet_start' => 'nullable|date_format:H:i',
            'preferences.*.quiet_end' => 'nullable|date_format:H:i',
        ];
    }
}
