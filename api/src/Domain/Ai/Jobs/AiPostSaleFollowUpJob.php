<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Domain\Ai\Enums\AiPostSaleScheduleType;
use Domain\Ai\Enums\AiPostSaleStatus;
use Domain\Ai\Models\AiPostSaleSchedule;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Enums\CRMNegotiationStatus;
use Domain\Shared\Concerns\HasJobDefaults;
use Domain\Shared\Infrastructure\WhatsApp\Contracts\WhatsAppAdapterInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to process post-sale follow-up messages.
 *
 * Runs daily to send D+1, D+7, D+30 follow-up messages to customers
 * who completed a purchase.
 */
class AiPostSaleFollowUpJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 3600;

    /**
     * Maximum number of schedules to process per run.
     */
    private const BATCH_SIZE = 100;

    /**
     * Execute the job.
     */
    public function handle(WhatsAppAdapterInterface $whatsApp): void
    {
        $schedules = $this->getPendingSchedules();

        Log::info('AiPostSaleFollowUpJob: Starting processing', [
            'pending_count' => $schedules->count(),
        ]);

        foreach ($schedules as $schedule) {
            $this->processSchedule($schedule, $whatsApp);
        }

        Log::info('AiPostSaleFollowUpJob: Completed processing');
    }

    /**
     * Get pending schedules that are due.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AiPostSaleSchedule>
     */
    private function getPendingSchedules(): \Illuminate\Database\Eloquent\Collection
    {
        return AiPostSaleSchedule::query()
            ->where('status', AiPostSaleStatus::PENDING)
            ->where('scheduled_at', '<=', now())
            ->with(['negotiation.contact', 'tenant.chatInstances'])
            ->orderBy('scheduled_at')
            ->limit(self::BATCH_SIZE)
            ->get();
    }

    /**
     * Process a single schedule.
     */
    private function processSchedule(
        AiPostSaleSchedule $schedule,
        WhatsAppAdapterInterface $whatsApp
    ): void {
        try {
            // Check skip conditions
            $skipReason = $this->getSkipReason($schedule);

            if ($skipReason !== null) {
                $this->markAsSkipped($schedule, $skipReason);

                return;
            }

            // Build message
            $message = $this->buildMessage($schedule);

            // Send via WhatsApp
            $contact = $schedule->negotiation->contact;

            if (! $contact || ! $contact->phone) {
                $this->markAsSkipped($schedule, 'Contact has no phone number');

                return;
            }

            $result = $whatsApp->sendTextMessage(
                instanceId: $this->getInstanceId($schedule),
                phone: $contact->phone,
                text: $message
            );

            if ($result['success'] ?? false) {
                $this->markAsSent($schedule, $result['message_id'] ?? null);
            } else {
                $this->markAsFailed($schedule, $result['error'] ?? 'Unknown error');
            }
        } catch (\Throwable $e) {
            Log::error('AiPostSaleFollowUpJob: Error processing schedule', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);

            $this->markAsFailed($schedule, $e->getMessage());
        }
    }

    /**
     * Check if schedule should be skipped.
     */
    private function getSkipReason(AiPostSaleSchedule $schedule): ?string
    {
        $negotiation = $schedule->negotiation;

        // Check if negotiation status changed from won
        if ($negotiation->status !== CRMNegotiationStatus::WON) {
            return 'Negotiation status changed from won';
        }

        // Check if contact opted out
        $contact = $negotiation->contact;
        if ($contact && ($contact->opt_out ?? false)) {
            return 'Contact opted out';
        }

        // Check if customer replied recently (last 24h for D+1, last 7 days for others)
        if ($this->hasRecentReply($schedule)) {
            return 'Customer replied recently';
        }

        // Check if there's an active ticket
        if ($this->hasActiveTicket($schedule)) {
            return 'Active ticket exists';
        }

        return null;
    }

    /**
     * Check if customer replied recently.
     */
    private function hasRecentReply(AiPostSaleSchedule $schedule): bool
    {
        $recentPeriod = match ($schedule->schedule_type) {
            AiPostSaleScheduleType::D1 => now()->subDay(),
            AiPostSaleScheduleType::D7 => now()->subDays(3),
            AiPostSaleScheduleType::D30 => now()->subDays(7),
        };

        $contactId = $schedule->negotiation->crm_contact_id;

        return ChatTicket::query()
            ->where('tenant_id', $schedule->tenant_id)
            ->where('contact_id', $contactId)
            ->whereHas('messages', fn ($q) => $q
                ->where('direction', 'incoming')
                ->where('created_at', '>=', $recentPeriod)
            )
            ->exists();
    }

    /**
     * Check if there's an active ticket.
     */
    private function hasActiveTicket(AiPostSaleSchedule $schedule): bool
    {
        $contactId = $schedule->negotiation->crm_contact_id;

        return ChatTicket::query()
            ->where('tenant_id', $schedule->tenant_id)
            ->where('contact_id', $contactId)
            ->whereIn('status', ['open', 'pending', 'waiting'])
            ->exists();
    }

    /**
     * Build the follow-up message.
     */
    private function buildMessage(AiPostSaleSchedule $schedule): string
    {
        // Use custom message if set
        if ($schedule->custom_message) {
            return $this->interpolateMessage($schedule->custom_message, $schedule);
        }

        // Use default message based on type
        $template = match ($schedule->schedule_type) {
            AiPostSaleScheduleType::D1 => 'Olá {contact_name}! Esperamos que esteja satisfeito com sua compra. Se tiver qualquer dúvida, estamos à disposição! 😊',
            AiPostSaleScheduleType::D7 => 'Olá {contact_name}! Já faz uma semana desde sua compra. Como está sendo sua experiência? Ficamos felizes em ajudar se precisar de algo!',
            AiPostSaleScheduleType::D30 => 'Olá {contact_name}! Passamos para saber como está sua experiência conosco. Sua opinião é muito importante para nós! ⭐',
        };

        return $this->interpolateMessage($template, $schedule);
    }

    /**
     * Interpolate message variables.
     */
    private function interpolateMessage(string $message, AiPostSaleSchedule $schedule): string
    {
        $contact = $schedule->negotiation->contact;
        $firstName = $contact ? explode(' ', $contact->name ?? 'Cliente')[0] : 'Cliente';

        return str_replace(
            ['{contact_name}', '{first_name}'],
            [$contact?->name ?? 'Cliente', $firstName],
            $message
        );
    }

    /**
     * Get WhatsApp instance ID for tenant.
     */
    private function getInstanceId(AiPostSaleSchedule $schedule): string
    {
        // Get the active instance for the tenant
        $instance = $schedule->tenant->chatInstances()->where('is_active', true)->first();

        if (! $instance) {
            $instance = $schedule->tenant->chatInstances()->first();
        }

        if (! $instance) {
            throw new \RuntimeException('No WhatsApp instance found for tenant');
        }

        return $instance->id;
    }

    /**
     * Mark schedule as sent.
     */
    private function markAsSent(AiPostSaleSchedule $schedule, ?string $messageId): void
    {
        $schedule->update([
            'status' => AiPostSaleStatus::SENT,
            'sent_at' => now(),
            'message_id' => $messageId,
            'attempts' => $schedule->attempts + 1,
        ]);

        Log::info('AiPostSaleFollowUpJob: Message sent', [
            'schedule_id' => $schedule->id,
            'type' => $schedule->schedule_type->value,
        ]);
    }

    /**
     * Mark schedule as skipped.
     */
    private function markAsSkipped(AiPostSaleSchedule $schedule, string $reason): void
    {
        $schedule->update([
            'status' => AiPostSaleStatus::SKIPPED,
            'error_message' => $reason,
            'metadata' => array_merge($schedule->metadata ?? [], [
                'skip_reason' => $reason,
                'skipped_at' => now()->toIso8601String(),
            ]),
        ]);

        Log::info('AiPostSaleFollowUpJob: Schedule skipped', [
            'schedule_id' => $schedule->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Mark schedule as failed.
     */
    private function markAsFailed(AiPostSaleSchedule $schedule, string $error): void
    {
        $schedule->update([
            'status' => AiPostSaleStatus::FAILED,
            'error_message' => $error,
            'attempts' => $schedule->attempts + 1,
        ]);

        Log::warning('AiPostSaleFollowUpJob: Message failed', [
            'schedule_id' => $schedule->id,
            'error' => $error,
        ]);
    }
}
