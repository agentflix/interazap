<?php

declare(strict_types=1);

namespace Domain\Billing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable for AI message usage threshold alerts (80% and 100%).
 */
final class UsageAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantName,
        public readonly int $threshold,
        public readonly int $current,
        public readonly int $limit,
        public readonly string $mode,
        public readonly ?string $overagePrice,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->threshold >= 100
            ? 'Limite de mensagens de IA atingido'
            : 'Aviso: 80% do limite de mensagens de IA atingido';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $view = $this->threshold >= 100
            ? 'emails.usage-alert-100'
            : 'emails.usage-alert-80';

        return new Content(
            view: $view,
            with: [
                'tenantName' => $this->tenantName,
                'threshold' => $this->threshold,
                'current' => $this->current,
                'limit' => $this->limit,
                'mode' => $this->mode,
                'overagePrice' => $this->overagePrice,
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
}
