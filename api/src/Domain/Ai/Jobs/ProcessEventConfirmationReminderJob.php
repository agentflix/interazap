<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Carbon\Carbon;
use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\CRM\Models\CRMEventClientConfirmation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job assíncrono para disparar lembrete de confirmação de evento.
 *
 * Quando o horário agendado para o lembrete chega (starts_at - minutes_before),
 * este job dispara um Autopilot run no ticket de origem para que o AI agent
 * envie a mensagem de confirmação ao cliente.
 */
final class ProcessEventConfirmationReminderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function retryUntil(): Carbon
    {
        return now()->addHours(2);
    }

    /**
     * @param  string  $confirmationId  UUID da confirmação.
     */
    public function __construct(
        public readonly string $confirmationId,
    ) {
        $this->onQueue(config('ai.autopilot.queue_name', 'ai'));
    }

    /**
     * Verifica a confirmação e despacha o run do Autopilot com contexto do evento.
     */
    public function handle(): void
    {
        $confirmation = CRMEventClientConfirmation::query()
            ->with(['crmEvent'])
            ->find($this->confirmationId);

        if (! $confirmation instanceof CRMEventClientConfirmation) {
            Log::warning('[ProcessEventConfirmationReminderJob] Confirmation not found', [
                'confirmation_id' => $this->confirmationId,
            ]);

            return;
        }

        // Idempotência: pular se já processado
        if ($confirmation->status !== CRMEventClientConfirmation::STATUS_PENDING) {
            Log::info('[ProcessEventConfirmationReminderJob] Skipping: confirmation already processed', [
                'confirmation_id' => $this->confirmationId,
                'status' => $confirmation->status,
            ]);

            return;
        }

        // Idempotência: pular se lembrete já enviado
        if ($confirmation->reminder_sent_at !== null) {
            Log::info('[ProcessEventConfirmationReminderJob] Skipping: reminder already sent', [
                'confirmation_id' => $this->confirmationId,
                'reminder_sent_at' => $confirmation->reminder_sent_at->toIso8601String(),
            ]);

            return;
        }

        // Marcar lembrete como enviado
        $confirmation->reminder_sent_at = now();
        $confirmation->save();

        // Sem ticket ativo — não há para onde enviar o autopilot
        $ticketId = (string) ($confirmation->chat_ticket_id ?? '');

        if ($ticketId === '') {
            Log::info('[ProcessEventConfirmationReminderJob] Skipping: no active ticket', [
                'confirmation_id' => $this->confirmationId,
            ]);

            return;
        }

        $event = $confirmation->crmEvent;
        $contact = $confirmation->crmContact;

        $confirmationContext = [
            'confirmation_id' => $confirmation->id,
            'event_id' => $event?->id,
            'event_title' => $event?->title,
            'event_starts_at' => $event?->starts_at?->toIso8601String(),
            'event_ends_at' => $event?->ends_at?->toIso8601String(),
            'contact_name' => $contact?->name,
            'contact_phone' => $contact?->phone,
        ];

        // Disparar autopilot run no ticket com contexto de confirmação
        DispatchAutopilotRunJob::dispatch(
            tenantId: $confirmation->tenant_id,
            triggerType: AutopilotTriggerType::SCHEDULED,
            context: [
                'ticket_id' => $ticketId,
                'confirmation_context' => $confirmationContext,
            ],
            sourceId: $this->confirmationId,
        );

        Log::info('[ProcessEventConfirmationReminderJob] Dispatched autopilot for confirmation', [
            'confirmation_id' => $this->confirmationId,
            'ticket_id' => $ticketId,
            'event_id' => $event?->id,
        ]);
    }
}
