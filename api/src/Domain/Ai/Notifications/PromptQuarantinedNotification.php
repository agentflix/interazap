<?php

declare(strict_types=1);

namespace Domain\Ai\Notifications;

use Domain\Ai\Models\AiPromptTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Notificação enviada aos SuperAdmins quando um prompt entra em quarentena.
 */
class PromptQuarantinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AiPromptTenant $tenantPrompt,
        public readonly string $reason,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->tenantPrompt->tenant;
        $promptExcerpt = Str::limit($this->tenantPrompt->content, 200);
        $tenantName = $tenant !== null ? $tenant->name : 'N/A';

        return (new MailMessage)
            ->subject('[AgentFlix] Prompt em Quarentena - Ação Necessária')
            ->greeting('Alerta de Segurança')
            ->line('Um prompt de tenant foi colocado em quarentena pelo sistema de segurança.')
            ->line('')
            ->line('**Tenant:** '.$tenantName)
            ->line('**ID do Prompt:** '.$this->tenantPrompt->id)
            ->line('**Motivo:** '.$this->reason)
            ->line('')
            ->line('**Trecho do Prompt:**')
            ->line('> '.$promptExcerpt)
            ->line('')
            ->action('Revisar no Painel', $this->getAdminUrl())
            ->line('')
            ->line('Por favor, revise este prompt e aprove ou rejeite-o manualmente.')
            ->salutation('Equipe AgentFlix');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $tenant = $this->tenantPrompt->tenant;

        return [
            'type' => 'prompt_quarantined',
            'tenant_prompt_id' => $this->tenantPrompt->id,
            'tenant_id' => $this->tenantPrompt->tenant_id,
            'tenant_name' => $tenant !== null ? $tenant->name : null,
            'reason' => $this->reason,
        ];
    }

    private function getAdminUrl(): string
    {
        $baseUrl = config('app.url', 'http://localhost');

        return $baseUrl.'/platform/ai/prompts/quarantine/'.$this->tenantPrompt->id;
    }
}
