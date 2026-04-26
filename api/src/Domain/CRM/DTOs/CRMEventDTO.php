<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Carbon\CarbonImmutable;
use Domain\CRM\Models\CRMEvent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for CRM calendar event.
 *
 * @readonly
 */
final readonly class CRMEventDTO
{
    /**
     * @param  array<int, array{type: string, id: string}>  $links
     * @param  array<int, array{auth_user_id?: string|null, crm_contact_id?: string|null, is_organizer?: bool, name?: string|null, email?: string|null}>  $participants
     * @param  array<int, array{minutes_before: int, notify_ui?: bool, notify_email?: bool, notify_push?: bool, notify_whatsapp?: bool, notify_webhook?: bool}>  $reminders
     */
    public function __construct(
        public string $tenant_id,
        public string $title,
        public CarbonImmutable $starts_at,
        public ?string $id = null,
        public ?string $auth_user_id = null,
        public ?string $description = null,
        public ?string $location = null,
        public ?CarbonImmutable $ends_at = null,
        public bool $is_all_day = false,
        public string $status = CRMEvent::STATUS_SCHEDULED,
        public string $type = CRMEvent::TYPE_MEETING,
        public string $recurrence = CRMEvent::RECURRENCE_NONE,
        public ?CarbonImmutable $recurrence_ends_at = null,
        public ?string $color = null,
        public array $links = [],
        public array $participants = [],
        public array $reminders = [],
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();
        $user = $request->user();

        return self::fromArray(
            tenantId: (string) $user?->tenant_id,
            data: $data,
            id: $request->route('id') ? (string) $request->route('id') : null,
            authUserId: $data['auth_user_id'] ?? $user?->id,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $tenantId, array $data, ?string $id = null, ?string $authUserId = null): self
    {
        return new self(
            tenant_id: $tenantId,
            title: (string) $data['title'],
            starts_at: CarbonImmutable::parse($data['starts_at']),
            id: $id,
            auth_user_id: $authUserId,
            description: $data['description'] ?? null,
            location: $data['location'] ?? null,
            ends_at: isset($data['ends_at']) ? CarbonImmutable::parse($data['ends_at']) : null,
            is_all_day: (bool) ($data['is_all_day'] ?? false),
            status: $data['status'] ?? CRMEvent::STATUS_SCHEDULED,
            type: $data['type'] ?? CRMEvent::TYPE_MEETING,
            recurrence: $data['recurrence'] ?? CRMEvent::RECURRENCE_NONE,
            recurrence_ends_at: isset($data['recurrence_ends_at'])
                ? CarbonImmutable::parse($data['recurrence_ends_at'])
                : null,
            color: $data['color'] ?? null,
            links: $data['links'] ?? [],
            participants: $data['participants'] ?? [],
            reminders: $data['reminders'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'auth_user_id' => $this->auth_user_id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'is_all_day' => $this->is_all_day,
            'status' => $this->status,
            'type' => $this->type,
            'recurrence' => $this->recurrence,
            'recurrence_ends_at' => $this->recurrence_ends_at,
            'color' => $this->color,
        ];
    }
}
