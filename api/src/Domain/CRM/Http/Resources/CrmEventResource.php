<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Resources;

use Domain\CRM\Models\CRMEventLink;
use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for CRM Event serialization.
 */
final class CRMEventResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        $linkTypes = array_flip(CRMEventLink::linkableTypes());

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->auth_user_id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'starts_at' => $this->iso($this->starts_at),
            'ends_at' => $this->iso($this->ends_at),
            'is_all_day' => (bool) $this->is_all_day,
            'status' => $this->status,
            'type' => $this->type,
            'recurrence' => $this->recurrence,
            'recurrence_ends_at' => $this->iso($this->recurrence_ends_at),
            'color' => $this->color,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
            'links' => $this->whenLoaded('links', fn () => $this->links->map(function ($link) use ($linkTypes) {
                return [
                    'id' => $link->id,
                    'type' => $linkTypes[$link->linkable_type] ?? $link->linkable_type,
                    'linkable_id' => $link->linkable_id,
                ];
            })),
            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($participant) => [
                'id' => $participant->id,
                'auth_user_id' => $participant->auth_user_id,
                'crm_contact_id' => $participant->crm_contact_id,
                'name' => $participant->name,
                'email' => $participant->email,
                'status' => $participant->status,
                'is_organizer' => (bool) $participant->is_organizer,
            ])),
            'reminders' => $this->whenLoaded('reminders', fn () => $this->reminders->map(fn ($reminder) => [
                'id' => $reminder->id,
                'minutes_before' => $reminder->minutes_before,
                'notify_ui' => (bool) $reminder->notify_ui,
                'notify_email' => (bool) $reminder->notify_email,
                'notify_push' => (bool) $reminder->notify_push,
                'notify_whatsapp' => (bool) $reminder->notify_whatsapp,
                'notify_webhook' => (bool) $reminder->notify_webhook,
                'scheduled_at' => $this->iso($reminder->scheduled_at),
                'is_sent' => (bool) $reminder->is_sent,
                'sent_at' => $this->iso($reminder->sent_at),
            ])),
        ];
    }
}
