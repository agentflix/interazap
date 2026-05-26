<?php

declare(strict_types=1);

namespace Domain\Billing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable para alertas de limiar de uso de mensagens IA (80% e 100%).
 *
 * O assunto e a view Blade são selecionados com base no valor do limiar atingido.
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

    /** Retorna o envelope com assunto adequado ao limiar atingido. */
    public function envelope(): Envelope
    {
        $subject = $this->threshold >= 100
            ? 'Limite de mensagens de IA atingido'
            : 'Aviso: 80% do limite de mensagens de IA atingido';

        return new Envelope(subject: $subject);
    }

    /** Retorna o conteúdo com a view e os dados de uso para o template Blade. */
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
     * Sem anexos para emails de alerta de uso.
     *
     * @return array<int, string>
     */
    public function attachments(): array
    {
        return [];
    }
}
