<?php

declare(strict_types=1);

namespace Domain\Ai\Notifications;

use Domain\Ai\Events\AiBudgetThresholdExceeded;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificação enviada quando o orçamento do Autopilot de um tenant atinge nível de alerta.
 *
 * Enviada por e-mail e canal in-app (database) quando o consumo ultrapassa
 * os limiares de warning ou critical configurados no sistema de budget.
 */
final class AiBudgetThresholdNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AiBudgetThresholdExceeded $event) {}

    /**
     * Define os canais de entrega da notificação (e-mail e banco de dados).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Constrói a mensagem de e-mail com os detalhes do alerta de orçamento.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $percentage = number_format($this->event->ratio * 100, 2);

        return (new MailMessage)
            ->subject('[InteraZap] Autopilot Budget '.strtoupper($this->event->level))
            ->greeting('Budget Alert')
            ->line('Autopilot budget threshold was reached for a tenant.')
            ->line('Tenant: '.$this->event->tenantId)
            ->line('Period: '.$this->event->period)
            ->line('Usage: $'.number_format($this->event->usage, 4))
            ->line('Limit: $'.number_format($this->event->limit, 4))
            ->line('Ratio: '.$percentage.'%')
            ->line('Level: '.strtoupper($this->event->level));
    }

    /**
     * Serializa os dados do alerta para armazenamento no banco de dados (canal database).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ai_budget_threshold',
            'tenant_id' => $this->event->tenantId,
            'period' => $this->event->period,
            'usage' => $this->event->usage,
            'limit' => $this->event->limit,
            'ratio' => $this->event->ratio,
            'level' => $this->event->level,
        ];
    }
}
