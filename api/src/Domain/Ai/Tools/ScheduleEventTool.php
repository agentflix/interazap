<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Carbon\Carbon;
use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Jobs\ProcessEventConfirmationReminderJob;
use Domain\Configuration\Models\ConfigurationSchedulingSetting;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMEvent;
use Domain\CRM\Models\CRMEventClientConfirmation;
use Domain\CRM\Models\CRMEventParticipant;
use Illuminate\Support\Str;

/**
 * Tool to schedule CRM events.
 */
class ScheduleEventTool implements AiToolInterface
{
    /**
     * Execute tool handler.
     */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $title = trim((string) ($input->parameters['title'] ?? ''));
        $type = (string) ($input->parameters['type'] ?? CRMEvent::TYPE_MEETING);
        $startsAt = (string) ($input->parameters['starts_at'] ?? '');
        $endsAt = (string) ($input->parameters['ends_at'] ?? '');
        $contactId = (string) ($input->parameters['contact_id'] ?? '');
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if ($title === '' || $startsAt === '') {
            return ToolResultDTO::failure('Title and starts_at are required');
        }

        if (! in_array($type, CRMEvent::types(), true)) {
            return ToolResultDTO::failure('Invalid event type');
        }

        try {
            $startDate = Carbon::parse($startsAt);
            $endDate = $endsAt !== '' ? Carbon::parse($endsAt) : $startDate->copy()->addHour();
        } catch (\Throwable) {
            return ToolResultDTO::failure('Invalid datetime format');
        }

        if ($endDate->lessThanOrEqualTo($startDate)) {
            return ToolResultDTO::failure('ends_at must be greater than starts_at');
        }

        $event = CRMEvent::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'title' => $title,
            'description' => ($description = trim((string) ($input->parameters['description'] ?? ''))) !== '' ? $description : null,
            'type' => $type,
            'status' => CRMEvent::STATUS_SCHEDULED,
            'starts_at' => $startDate,
            'ends_at' => $endDate,
            'recurrence' => CRMEvent::RECURRENCE_NONE,
        ]);

        if ($contactId !== '') {
            $contact = CRMContact::query()
                ->where('tenant_id', $tenantId)
                ->find($contactId);

            if (! $contact) {
                return ToolResultDTO::failure('Contact not found');
            }

            CRMEventParticipant::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'crm_event_id' => $event->id,
                'crm_contact_id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'status' => CRMEventParticipant::STATUS_PENDING,
                'is_organizer' => false,
            ]);
        }

        // Create client confirmation and schedule reminder job
        $confirmationId = null;
        $setting = ConfigurationSchedulingSetting::forTenant($tenantId);

        if ($setting->event_confirmation_enabled) {
            $ticketId = (string) ($input->context['ticket_id'] ?? '');
            $confirmation = CRMEventClientConfirmation::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'crm_event_id' => $event->id,
                'crm_contact_id' => $contactId !== '' ? $contactId : null,
                'chat_ticket_id' => $ticketId !== '' ? $ticketId : null,
                'status' => CRMEventClientConfirmation::STATUS_PENDING,
                'minutes_before' => $setting->event_confirmation_advance_minutes,
            ]);

            $scheduledAt = $event->starts_at->copy()->subMinutes($setting->event_confirmation_advance_minutes);

            if ($scheduledAt->isFuture()) {
                ProcessEventConfirmationReminderJob::dispatch($confirmation->id)
                    ->delay($scheduledAt);
            }

            $confirmationId = $confirmation->id;
        }

        return ToolResultDTO::success(
            message: "Event '{$event->title}' scheduled",
            data: [
                'event_id' => $event->id,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'confirmation_id' => $confirmationId,
            ]
        );
    }

    /**
     * Return tool unique name.
     */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::SCHEDULE_EVENT;
    }

    /**
     * Return tool description.
     */
    public function getDescription(): string
    {
        return 'Schedules a CRM event and optionally links a contact participant.';
    }

    /**
     * Return expected tool parameters.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'title' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Event title',
            ],
            'type' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Event type (meeting, call, task, deadline, reminder, other)',
            ],
            'starts_at' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Start datetime in ISO format',
            ],
            'ends_at' => [
                'type' => 'string',
                'required' => false,
                'description' => 'End datetime in ISO format',
            ],
            'contact_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Optional contact UUID to link as participant',
            ],
            'description' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Event description',
            ],
        ];
    }
}
