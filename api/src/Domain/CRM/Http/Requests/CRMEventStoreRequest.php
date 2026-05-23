<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Carbon\CarbonImmutable;
use Domain\CRM\Models\CRMEvent;
use Domain\Shared\Rules\TenantExistsRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para criação de eventos na agenda CRM.
 */
class CRMEventStoreRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = implode(',', CRMEvent::statuses());
        $types = implode(',', CRMEvent::types());
        $recurrences = implode(',', CRMEvent::recurrences());

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['required', 'date', $this->overlapRule()],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_all_day' => ['nullable', 'boolean'],
            'status' => ['nullable', "in:{$statuses}"],
            'type' => ['nullable', "in:{$types}"],
            'recurrence' => ['nullable', "in:{$recurrences}"],
            'recurrence_ends_at' => ['nullable', 'date', 'after:starts_at'],
            'color' => ['nullable', 'string', 'max:7'],
            'auth_user_id' => ['nullable', 'uuid', new TenantExistsRule('auth_users')],
            'links' => ['nullable', 'array'],
            'links.*.type' => ['required_with:links', 'in:contact,company,deal,ticket'],
            'links.*.id' => ['required_with:links', 'uuid'],
            'participants' => ['nullable', 'array'],
            'participants.*.auth_user_id' => ['nullable', 'uuid', new TenantExistsRule('auth_users')],
            'participants.*.crm_contact_id' => ['nullable', 'uuid', new TenantExistsRule('crm_contacts')],
            'participants.*.is_organizer' => ['nullable', 'boolean'],
            'participants.*.name' => ['nullable', 'string', 'max:255'],
            'participants.*.email' => ['nullable', 'email', 'max:255'],
            'reminders' => ['nullable', 'array'],
            'reminders.*.minutes_before' => ['required_with:reminders', 'integer', 'min:0', 'max:10080'],
            'reminders.*.notify_ui' => ['nullable', 'boolean'],
            'reminders.*.notify_email' => ['nullable', 'boolean'],
            'reminders.*.notify_push' => ['nullable', 'boolean'],
            'reminders.*.notify_whatsapp' => ['nullable', 'boolean'],
            'reminders.*.notify_webhook' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'O título do evento é obrigatório.',
            'starts_at.required' => 'A data de início é obrigatória.',
            'ends_at.after_or_equal' => 'A data de término deve ser igual ou posterior à data de início.',
            'recurrence_ends_at.after' => 'A data final de recorrência deve ser posterior à data de início.',
        ];
    }

    private function overlapRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $tenantId = $this->user()?->tenant_id;
            $userId = $this->input('auth_user_id') ?? $this->user()?->id;

            if ($tenantId === null || $userId === null) {
                return;
            }

            $startsAt = CarbonImmutable::parse((string) $value);
            $endsAtInput = $this->input('ends_at');
            $endsAt = $endsAtInput ? CarbonImmutable::parse((string) $endsAtInput) : $startsAt->addHour();
            $currentId = $this->route('id') ? (string) $this->route('id') : null;

            $exists = CRMEvent::query()
                ->where('tenant_id', $tenantId)
                ->where('auth_user_id', $userId)
                ->where('status', '!=', CRMEvent::STATUS_CANCELLED)
                ->when($currentId, fn ($query) => $query->where('id', '!=', $currentId))
                ->whereRaw('(starts_at < ? AND COALESCE(ends_at, starts_at) > ?)', [$endsAt, $startsAt])
                ->exists();

            if ($exists) {
                $fail('Já existe um evento agendado para este horário.');
            }
        };
    }
}
