<?php

declare(strict_types=1);

namespace Domain\Configuration\Mail;

use Domain\Configuration\Models\ConfigurationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail padrão para entrega de notificação por canal email.
 */
final class NotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ConfigurationNotification $notification,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.default',
            with: [
                'notification' => $this->notification,
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
