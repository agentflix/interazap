<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Events\BillingCollectionSentEvent;
use Domain\Billing\Mail\BillingCollectionMail;
use Domain\Billing\Models\BillingCollectionLog;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Services\BillingGatewayService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Envia lembretes de cobrança para tenants com fatura em atraso.
 */
final class BillingSendRemindersAction
{
    public function __construct(
        private readonly BillingGatewayService $gatewayService,
    ) {}

    /**
     * Percorre tenants com fatura vencida e envia lembretes pelos canais configurados.
     *
     * Respeita horário de silêncio (quiet hours) e limita um envio por tenant por dia.
     * Em modo dry-run os emails/WhatsApps não são enviados, mas os contadores são calculados.
     *
     * @param  bool  $dryRun  Se verdadeiro, apenas simula sem enviar notificações
     * @return array{processed:int,sent:int,skipped:int,failed:int}
     */
    public function handle(bool $dryRun = false): array
    {
        if (! $this->isFeatureEnabled()) {
            return ['processed' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        if ($this->isQuietHours()) {
            return ['processed' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $result = ['processed' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];

        /** @var Collection<int, object{tenant_id:string, oldest_due_date:string}> $overdueByTenant */
        $overdueByTenant = BillingInvoice::query()
            ->withoutGlobalScopes()
            ->selectRaw('tenant_id, MIN(due_date) as oldest_due_date')
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', today())
            ->groupBy('tenant_id')
            ->get();

        foreach ($overdueByTenant as $row) {
            $tenant = PlatformTenant::query()->withoutGlobalScopes()->find($row->tenant_id);
            if (! $tenant instanceof PlatformTenant) {
                continue;
            }

            $result['processed']++;

            $invoice = BillingInvoice::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'overdue')
                ->orderBy('due_date')
                ->first();

            if (! $invoice instanceof BillingInvoice) {
                $result['skipped']++;

                continue;
            }

            $daysOverdue = (int) Carbon::parse($invoice->due_date)->startOfDay()->diffInDays(now()->startOfDay());
            $template = $this->resolveTemplateByDay($daysOverdue);

            if ($template === null) {
                $result['skipped']++;

                continue;
            }

            if ($tenant->last_collection_sent_at !== null && $tenant->last_collection_sent_at->isToday()) {
                $result['skipped']++;

                continue;
            }

            $channels = $template['channels'];

            $sendStatus = $this->sendForChannels(
                tenant: $tenant,
                invoice: $invoice,
                templateId: (string) $template['template'],
                channels: $channels,
                daysOverdue: $daysOverdue,
                dryRun: $dryRun,
            );

            if ($sendStatus['failed'] > 0) {
                $result['failed'] += $sendStatus['failed'];
            } else {
                $result['sent'] += $sendStatus['sent'];
            }

            if (! $dryRun) {
                $tenant->forceFill([
                    'last_collection_sent_at' => now(),
                    'collection_count' => (int) $tenant->collection_count + 1,
                ])->save();
            }
        }

        return $result;
    }

    /**
     * Envia o lembrete de cobrança pelos canais informados para o tenant.
     *
     * @param  list<string>  $channels  Canais de envio ('email', 'whatsapp')
     * @return array{sent:int,failed:int}
     */
    private function sendForChannels(
        PlatformTenant $tenant,
        BillingInvoice $invoice,
        string $templateId,
        array $channels,
        int $daysOverdue,
        bool $dryRun,
    ): array {
        $status = ['sent' => 0, 'failed' => 0];

        foreach ($channels as $channel) {
            if ($channel === 'email') {
                $emails = $this->resolveTenantEmails($tenant);

                foreach ($emails as $email) {
                    $deliveryStatus = $dryRun
                        ? 'sent'
                        : $this->sendEmailReminder($email, $tenant, $invoice, $templateId, $daysOverdue);

                    if (! $dryRun) {
                        $this->recordCollectionLog($tenant, $invoice, $templateId, 'email', $email, $deliveryStatus);
                    }

                    if ($deliveryStatus === 'sent') {
                        $status['sent']++;
                    } else {
                        $status['failed']++;
                    }
                }

                continue;
            }

            if ($channel === 'whatsapp' && is_string($tenant->phone) && $tenant->phone !== '') {
                $deliveryStatus = $dryRun
                    ? 'sent'
                    : $this->sendWhatsappReminder($tenant, $invoice, $templateId, $daysOverdue);

                if (! $dryRun) {
                    $this->recordCollectionLog($tenant, $invoice, $templateId, 'whatsapp', (string) $tenant->phone, $deliveryStatus);
                }

                if ($deliveryStatus === 'sent') {
                    $status['sent']++;
                } else {
                    $status['failed']++;
                }
            }
        }

        return $status;
    }

    /**
     * Retorna lista deduplicada de e-mails do tenant: email primário + e-mails de usuários ativos.
     *
     * @return list<string>
     */
    private function resolveTenantEmails(PlatformTenant $tenant): array
    {
        $emails = [];

        if (is_string($tenant->primary_email) && $tenant->primary_email !== '') {
            $emails[] = $tenant->primary_email;
        }

        $adminEmails = AuthUser::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('email')
            ->filter(static fn (mixed $email): bool => is_string($email) && $email !== '')
            ->map(static fn (mixed $email): string => (string) $email)
            ->all();

        return array_values(array_unique([...$emails, ...$adminEmails]));
    }

    /** Envia o e-mail de lembrete e retorna 'sent' ou 'failed'. */
    private function sendEmailReminder(
        string $email,
        PlatformTenant $tenant,
        BillingInvoice $invoice,
        string $templateId,
        int $daysOverdue,
    ): string {
        try {
            Mail::to($email)->send(new BillingCollectionMail(
                templateId: $templateId,
                tenant: $tenant,
                invoice: $invoice,
                daysOverdue: $daysOverdue,
            ));

            return 'sent';
        } catch (\Throwable) {
            return 'failed';
        }
    }

    /** Envia o lembrete via WhatsApp pelo gateway e retorna 'sent' ou 'failed'. */
    private function sendWhatsappReminder(
        PlatformTenant $tenant,
        BillingInvoice $invoice,
        string $templateId,
        int $daysOverdue,
    ): string {
        $response = $this->gatewayService->sendCollectionMessage(
            tenantId: (string) $tenant->id,
            phone: (string) $tenant->phone,
            templateId: $templateId,
            variables: [
                'tenant_name' => (string) $tenant->name,
                'reference_month' => (string) $invoice->reference_month,
                'amount' => (float) $invoice->amount,
                'due_date' => (string) $invoice->due_date,
                'payment_url' => (string) $invoice->payment_url,
                'days_overdue' => $daysOverdue,
            ],
        );

        return $response['success'] ? 'sent' : 'failed';
    }

    /** Persiste o registro de envio de cobrança e dispara o evento de auditoria. */
    private function recordCollectionLog(
        PlatformTenant $tenant,
        BillingInvoice $invoice,
        string $templateId,
        string $channel,
        string $recipient,
        string $status,
    ): void {
        BillingCollectionLog::query()->create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'template_id' => $templateId,
            'channel' => $channel,
            'recipient' => $recipient,
            'status' => $status,
            'provider_message_id' => null,
            'metadata' => null,
        ]);

        BillingCollectionSentEvent::dispatch(
            (string) $tenant->id,
            (string) $invoice->id,
            $templateId,
            $channel,
            $recipient,
            $status,
        );
    }

    /**
     * Retorna o template e canais configurados para o número de dias em atraso, ou null se não houver regra.
     *
     * @return array{template:string,channels:list<string>}|null
     */
    private function resolveTemplateByDay(int $daysOverdue): ?array
    {
        $reminders = config('billing.delinquency.reminders', []);
        if (! is_array($reminders)) {
            return null;
        }

        foreach ($reminders as $reminder) {
            if (! is_array($reminder)) {
                continue;
            }

            $day = (int) ($reminder['day'] ?? 0);
            if ($day !== $daysOverdue) {
                continue;
            }

            $template = (string) ($reminder['template'] ?? 'friendly_reminder');
            /** @var list<string> $channels */
            $channels = is_array($reminder['channels'] ?? null) ? array_values($reminder['channels']) : ['email'];

            return ['template' => $template, 'channels' => $channels];
        }

        return null;
    }

    /** Retorna true se o horário atual estiver dentro do período de silêncio configurado. */
    private function isQuietHours(): bool
    {
        $start = (int) config('billing.delinquency.quiet_hours.start', 18);
        $end = (int) config('billing.delinquency.quiet_hours.end', 9);
        $hour = (int) now()->format('G');

        if ($start === $end) {
            return false;
        }

        if ($start > $end) {
            return $hour >= $start || $hour < $end;
        }

        return $hour >= $start && $hour < $end;
    }

    /** Verifica se o módulo de inadimplência está habilitado pela config. */
    private function isFeatureEnabled(): bool
    {
        return (bool) config('billing.delinquency.enabled', true);
    }
}
