<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Negotiation Task serialization.
 */
final class CRMNegotiationTaskResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'negotiation_id' => $this->crm_negotiation_id,
            'crm_negotiation_id' => $this->crm_negotiation_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'due_date' => $this->iso($this->due_date),
            'assigned_to' => $this->auth_user_id,
            'user_id' => $this->auth_user_id,
            'action_type' => $this->action_type,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'reminder_at' => $this->iso($this->reminder_at),
            'add_to_agenda' => (bool) ($this->add_to_agenda ?? false),
            'agenda_event_id' => $this->agenda_event_id,
            'notify_ui' => (bool) ($this->notify_ui ?? false),
            'notify_email' => (bool) ($this->notify_email ?? false),
            'notify_push' => (bool) ($this->notify_push ?? false),
            'notify_whatsapp' => (bool) ($this->notify_whatsapp ?? false),
            'is_completed' => (bool) ($this->is_completed ?? false),
            'completed_at' => $this->iso($this->completed_at),
            'priority' => $this->priority ?? 'medium',
            'negotiation' => $this->when(
                $this->resource->relationLoaded('negotiation'),
                fn () => [
                    'id' => $this->negotiation?->id,
                    'title' => $this->negotiation?->title,
                    'crm_company' => $this->when(
                        $this->negotiation?->relationLoaded('company'),
                        fn () => [
                            'id' => $this->negotiation?->company?->id,
                            'name' => $this->negotiation?->company?->name,
                        ]
                    ),
                ]
            ),
            'user' => $this->when(
                $this->resource->relationLoaded('assignee'),
                fn () => [
                    'id' => $this->assignee?->id,
                    'name' => $this->assignee?->name,
                ]
            ),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
