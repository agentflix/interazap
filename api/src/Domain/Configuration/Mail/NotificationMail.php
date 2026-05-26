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

    /** Cria o Mailable com a notificação a ser enviada. */
    public function __construct(
        public readonly ConfigurationNotification $notification,
    ) {}

    /** Retorna o envelope do e-mail com assunto baseado no título da notificação. */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->title,
        );
    }

    /** Retorna o conteúdo do e-mail utilizando a view padrão de notificações. */
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
     * Retorna os anexos do e-mail (nenhum por padrão).
     *
     * @return array<int, string>
     */
    public function attachments(): array
    {
        return [];
    }
}
