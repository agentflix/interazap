<?php

declare(strict_types=1);

namespace Domain\Billing\Mail;

use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Models\BillingPurgeReport;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable para comunicação de cobrança e lockout.
 */
final class BillingCollectionMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $templateId,
        public readonly PlatformTenant $tenant,
        public readonly ?BillingInvoice $invoice,
        public readonly int $daysOverdue,
        public readonly ?BillingPurgeReport $purgeReport = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectByTemplate($this->templateId),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->viewByTemplate($this->templateId),
            with: [
                'tenant' => $this->tenant,
                'invoice' => $this->invoice,
                'daysOverdue' => $this->daysOverdue,
                'purgeReport' => $this->purgeReport,
                'purgeDeadline' => $this->tenant->billing_purge_deadline,
                'graceDeadline' => $this->tenant->billing_grace_deadline,
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    public function attachments(): array
    {
        return [];
    }

    private function viewByTemplate(string $templateId): string
    {
        return match ($templateId) {
            'friendly_reminder' => 'emails.billing.friendly-reminder',
            'urgent_reminder' => 'emails.billing.urgent-reminder',
            'lockout_warning' => 'emails.billing.lockout-warning',
            'lockout_notice' => 'emails.billing.lockout-notice',
            'purge_warning' => 'emails.billing.purge-warning',
            'purge_final' => 'emails.billing.purge-final',
            default => 'emails.billing.friendly-reminder',
        };
    }

    private function subjectByTemplate(string $templateId): string
    {
        return match ($templateId) {
            'friendly_reminder' => 'Lembrete de pagamento pendente',
            'urgent_reminder' => 'Ação necessária: fatura em atraso',
            'lockout_warning' => 'Aviso: bloqueio por inadimplência em breve',
            'lockout_notice' => 'Conta temporariamente bloqueada por inadimplência',
            'purge_warning' => 'Último aviso antes da exclusão dos dados',
            'purge_final' => 'Confirmação de exclusão de dados por inadimplência',
            default => 'Atualização de cobrança',
        };
    }
}
