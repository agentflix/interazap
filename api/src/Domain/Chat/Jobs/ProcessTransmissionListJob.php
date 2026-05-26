<?php

declare(strict_types=1);

namespace Domain\Chat\Jobs;

use Domain\Chat\Models\ChatTransmissionList;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job de processamento de lista de transmissão.
 * Executa o envio em lotes para evitar timeouts e respeitar rate limits.
 */
class ProcessTransmissionListJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Tempo em segundos até o bloqueio de unicidade ser liberado.
     */
    public int $uniqueFor = 300;

    /**
     * @param  ChatTransmissionList  $transmissionList  Lista de transmissão a ser processada.
     */
    public function __construct(
        public ChatTransmissionList $transmissionList
    ) {}

    /**
     * ID único do job baseado no ID da lista de transmissão.
     */
    public function uniqueId(): string
    {
        return $this->transmissionList->id;
    }

    /**
     * Processa o próximo lote de contatos pendentes e agenda o lote seguinte se necessário.
     */
    public function handle(ChatGatewayService $gateway): void
    {
        $this->transmissionList->refresh();
        if ($this->transmissionList->status !== 'running') {
            return;
        }

        // Get next batch of pending contacts
        $pendingContacts = $this->transmissionList->contacts()
            ->where('status', 'pending')
            ->with('contact')
            ->take(20) // Batch size small to be safe
            ->get();

        if ($pendingContacts->isEmpty()) {
            $this->transmissionList->status = 'completed';
            $this->transmissionList->save();

            return;
        }

        foreach ($pendingContacts as $transmissionListContact) {
            try {
                $contact = $transmissionListContact->contact;

                // Skip if no valid number
                $phone = $contact->whatsapp ?? $contact->phone;
                if (! $phone) {
                    $transmissionListContact->update(['status' => 'failed', 'error' => 'No phone number']);

                    continue;
                }

                $message = $this->replaceVariables($this->transmissionList->message ?? '', $contact);
                $instanceId = $this->transmissionList->instance_id;

                $token = $this->getInstanceToken($this->transmissionList->tenant_id, $instanceId);

                if (! $token) {
                    $this->transmissionList->status = 'failed';
                    $this->transmissionList->metadata = array_merge($this->transmissionList->metadata ?? [], ['error' => 'Instance token not found']);
                    $this->transmissionList->save();

                    return;
                }

                // Send Message
                $gateway->sendText($token, [
                    'number' => $phone,
                    'text' => $message,
                ]);

                $transmissionListContact->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error' => null,
                ]);

                // Update transmission list stats
                $deliveries = ($this->transmissionList->metadata['deliveries'] ?? 0) + 1;
                $metadata = $this->transmissionList->metadata ?? [];
                $metadata['deliveries'] = $deliveries;
                $this->transmissionList->update(['metadata' => $metadata]);

            } catch (\Throwable $e) {
                $transmissionListContact->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Dispatch next batch
        if ($this->transmissionList->contacts()->where('status', 'pending')->exists()) {
            self::dispatch($this->transmissionList)->delay(now()->addSeconds(5));
        } else {
            $this->transmissionList->status = 'completed';
            $this->transmissionList->save();
        }
    }

    /**
     * Substitui variáveis de personalização ({{name}}, {{phone}}, etc.) pelos dados do contato.
     */
    private function replaceVariables(string $message, object $contact): string
    {
        $vars = [
            '{{name}}' => $contact->name,
            '{{nome}}' => $contact->name,
            '{{phone}}' => $contact->phone,
            '{{email}}' => $contact->email,
        ];

        return str_replace(array_keys($vars), array_values($vars), $message);
    }

    /**
     * Obtém o token de webhook da instância de envio do tenant.
     */
    private function getInstanceToken(string $tenantId, ?string $instanceId): ?string
    {
        // Simple lookup. Ideally inject a Repository or Service.
        // Using Facade or Model directly for simplicity in Job.
        $query = \Domain\Chat\Models\ChatInstance::query()
            ->where('tenant_id', $tenantId);

        if ($instanceId) {
            $query->where('id', $instanceId);
        } else {
            // Default to first active instance if not specified
            $query->where('is_active', true);
        }

        return $query->value('webhook_token'); // Assuming webhook_token is the token used for sending
    }
}
