<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO para tarefa vinculada a uma negociação.
 *
 * @readonly
 */
final readonly class CRMNegotiationTaskDTO
{
    public function __construct(
        public string $crm_negotiation_id,
        public string $title,
        public ?string $description = null,
        public ?string $due_date = null,
        public string $status = 'pending',
        public ?string $auth_user_id = null,
        public ?string $action_type = null,
        public ?string $start_time = null,
        public ?string $end_time = null,
        public ?string $reminder_at = null,
        public bool $add_to_agenda = false,
        public ?string $agenda_event_id = null,
        public bool $notify_ui = false,
        public bool $notify_email = false,
        public bool $notify_push = false,
        public bool $notify_whatsapp = false,
        public string $priority = 'medium',
    ) {}

    /** Cria DTO a partir de um FormRequest já validado. */
    public static function fromRequest(FormRequest $request, string $negotiationId): self
    {
        return self::fromArray($request->validated(), $negotiationId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $negotiationId = ''): self
    {
        return new self(
            crm_negotiation_id: $negotiationId ?: ($data['crm_negotiation_id'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            description: $data['description'] ?? null,
            due_date: $data['due_date'] ?? null,
            status: (string) ($data['status'] ?? 'pending'),
            auth_user_id: $data['auth_user_id'] ?? null,
            action_type: $data['action_type'] ?? null,
            start_time: $data['start_time'] ?? null,
            end_time: $data['end_time'] ?? null,
            reminder_at: $data['reminder_at'] ?? null,
            add_to_agenda: (bool) ($data['add_to_agenda'] ?? false),
            agenda_event_id: $data['agenda_event_id'] ?? null,
            notify_ui: (bool) ($data['notify_ui'] ?? false),
            notify_email: (bool) ($data['notify_email'] ?? false),
            notify_push: (bool) ($data['notify_push'] ?? false),
            notify_whatsapp: (bool) ($data['notify_whatsapp'] ?? false),
            priority: (string) ($data['priority'] ?? 'medium'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'crm_negotiation_id' => $this->crm_negotiation_id,
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'auth_user_id' => $this->auth_user_id,
            'action_type' => $this->action_type,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'reminder_at' => $this->reminder_at,
            'add_to_agenda' => $this->add_to_agenda,
            'agenda_event_id' => $this->agenda_event_id,
            'notify_ui' => $this->notify_ui,
            'notify_email' => $this->notify_email,
            'notify_push' => $this->notify_push,
            'notify_whatsapp' => $this->notify_whatsapp,
            'priority' => $this->priority,
        ];
    }
}
