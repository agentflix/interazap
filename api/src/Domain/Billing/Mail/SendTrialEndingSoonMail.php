<?php

declare(strict_types=1);

namespace Domain\Billing\Mail;

use Domain\Platform\Models\PlatformTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable de lembrete de trial próximo do vencimento.
 */
final class SendTrialEndingSoonMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly PlatformTenant $tenant) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seu trial está próximo do vencimento');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.billing.trial-ending-soon',
            with: ['tenant' => $this->tenant],
        );
    }

    /**
     * @return array<int, string>
     */
    public function attachments(): array
    {
        return [];
    }
}
