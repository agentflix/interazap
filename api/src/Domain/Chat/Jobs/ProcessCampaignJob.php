<?php

declare(strict_types=1);

namespace Domain\Chat\Jobs;

use Domain\Chat\Models\ChatCampaign;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job de processamento de campanha.
 * Executa o envio em lotes para evitar timeouts e respeitar rate limits.
 */
class ProcessCampaignJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 300;

    public function __construct(
        public ChatCampaign $campaign
    ) {}

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return $this->campaign->id;
    }

    public function handle(ChatGatewayService $gateway): void
    {
        // ... (methods implementation remains similar, ensuring type safety in inner logic if needed)
        // Actually replaceVariables method signature is the main issue.
        // Let's fix property type first.

        $this->campaign->refresh();
        if ($this->campaign->status !== 'running') {
            return;
        }

        // Get next batch of pending contacts
        $pendingContacts = $this->campaign->contacts()
            ->where('status', 'pending')
            ->with('contact')
            ->take(20) // Batch size small to be safe
            ->get();

        if ($pendingContacts->isEmpty()) {
            $this->campaign->status = 'completed';
            $this->campaign->save();

            return;
        }

        foreach ($pendingContacts as $campaignContact) {
            try {
                $contact = $campaignContact->contact;

                // Skip if no valid number
                $phone = $contact->whatsapp ?? $contact->phone;
                if (! $phone) {
                    $campaignContact->update(['status' => 'failed', 'error' => 'No phone number']);

                    continue;
                }

                $message = $this->replaceVariables($this->campaign->message ?? '', $contact);
                $instanceId = $this->campaign->instance_id;

                $token = $this->getInstanceToken($this->campaign->tenant_id, $instanceId);

                if (! $token) {
                    $this->campaign->status = 'failed';
                    $this->campaign->metadata = array_merge($this->campaign->metadata ?? [], ['error' => 'Instance token not found']);
                    $this->campaign->save();

                    return;
                }

                // Send Message
                $gateway->sendText($token, [
                    'number' => $phone,
                    'text' => $message,
                ]);

                $campaignContact->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error' => null,
                ]);

                // Update campaign stats
                $deliveries = ($this->campaign->metadata['deliveries'] ?? 0) + 1;
                $metadata = $this->campaign->metadata ?? [];
                $metadata['deliveries'] = $deliveries;
                $this->campaign->update(['metadata' => $metadata]);

                // Rate limiting handled by delay in next batch dispatch
                // Removed sleep(1) to prevent worker blocking

            } catch (\Throwable $e) {
                $campaignContact->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Dispatch next batch
        if ($this->campaign->contacts()->where('status', 'pending')->exists()) {
            self::dispatch($this->campaign)->delay(now()->addSeconds(5));
        } else {
            $this->campaign->status = 'completed';
            $this->campaign->save();
        }
    }

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
