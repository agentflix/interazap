<?php

declare(strict_types=1);

namespace Domain\Chat\Jobs;

use Domain\Chat\Contracts\ChatWhatsAppGatewayInterface;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Services\ChatBroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Shared\Jobs\Middleware\RateLimitedJob;
use Shared\Jobs\Traits\RetryableWithBackoff;

/**
 * Job for sending WhatsApp messages with retry patterns and rate limiting.
 *
 * This job handles the asynchronous delivery of WhatsApp messages,
 * with exponential backoff retry strategy and rate limiting to
 * comply with WhatsApp API limits.
 */
final class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RetryableWithBackoff;
    use SerializesModels;

    /**
     * The tenant identifier.
     */
    private readonly string $tenantId;

    /**
     * Get the WhatsApp-specific backoff delays.
     * More aggressive retries for transient API errors.
     *
     * @return array<int, int>
     */
    protected function getBackoffDelays(): array
    {
        return [5, 15, 45, 120, 300];
    }

    /**
     * Create a new job instance.
     *
     * @param  string  $messageId  The message ID to send.
     * @param  string  $tenantId  The tenant identifier.
     * @param  string  $instanceId  The WhatsApp instance ID.
     * @param  string  $to  The recipient phone number.
     * @param  string  $content  The message content.
     * @param  string  $type  The message type (text, image, document, etc.).
     * @param  array<string, mixed>  $metadata  Additional metadata.
     */
    public function __construct(
        private readonly string $messageId,
        string $tenantId,
        private readonly string $instanceId,
        private readonly string $to,
        private readonly string $content,
        private readonly string $type = 'text',
        private readonly array $metadata = [],
    ) {
        $this->tenantId = $tenantId;
        // Override trait defaults for WhatsApp-specific configuration
        $this->timeout = 60;
        $this->maxExceptions = 4;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            RateLimitedJob::forWhatsApp(),
        ];
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return "whatsapp:{$this->messageId}";
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'whatsapp',
            "tenant:{$this->tenantId}",
            "instance:{$this->instanceId}",
            'message:send',
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(ChatWhatsAppGatewayInterface $gateway, ChatBroadcastService $broadcast): void
    {
        Log::info('[SendWhatsAppMessageJob] Sending message', [
            'message_id' => $this->messageId,
            'tenant_id' => $this->tenantId,
            'instance_id' => $this->instanceId,
            'to' => $this->maskPhoneNumber($this->to),
            'type' => $this->type,
        ]);

        try {
            $result = $gateway->sendMessage(
                instanceId: $this->instanceId,
                to: $this->to,
                content: $this->content,
                type: $this->type,
                metadata: $this->metadata,
            );

            $this->updateMessageStatus('sent', $result);

            $message = ChatMessage::query()
                ->select(['id', 'ticket_id', 'metadata'])
                ->where('tenant_id', $this->tenantId)
                ->where('id', $this->messageId)
                ->first();

            if ($message !== null) {
                $broadcast->emitMessageStatus([
                    'message_id' => $this->messageId,
                    'ticket_id' => (string) $message->ticket_id,
                    'tenant_id' => $this->tenantId,
                    'status' => 'sent',
                    'client_message_id' => $message->metadata['client_message_id'] ?? null,
                    'sent_at' => now()->toIso8601String(),
                ]);
            } else {
                Log::warning('[SendWhatsAppMessageJob] Message not found for broadcast', [
                    'message_id' => $this->messageId,
                ]);
            }

            Log::info('[SendWhatsAppMessageJob] Message sent successfully', [
                'message_id' => $this->messageId,
                'provider_message_id' => $result['id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->handleError($e);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[SendWhatsAppMessageJob] Job failed permanently', [
            'message_id' => $this->messageId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->updateMessageStatus('failed', [
            'error' => $exception->getMessage(),
            'failed_at' => now()->toIso8601String(),
        ]);

        $message = ChatMessage::query()
            ->select(['id', 'ticket_id', 'metadata'])
            ->where('tenant_id', $this->tenantId)
            ->where('id', $this->messageId)
            ->first();

        if ($message !== null) {
            /** @var ChatBroadcastService $broadcast */
            $broadcast = app(ChatBroadcastService::class);
            $broadcast->emitMessageStatus([
                'message_id' => $this->messageId,
                'ticket_id' => (string) $message->ticket_id,
                'tenant_id' => $this->tenantId,
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'client_message_id' => $message->metadata['client_message_id'] ?? null,
            ]);
        }
    }

    /**
     * Determine if the exception should be retried.
     */
    protected function shouldFailImmediately(\Throwable $exception): bool
    {
        // Don't retry on invalid phone numbers or content
        if ($exception instanceof \InvalidArgumentException) {
            return true;
        }

        // Don't retry on authentication errors
        if (str_contains($exception->getMessage(), 'authentication')) {
            return true;
        }

        // Don't retry on invalid instance
        if (str_contains($exception->getMessage(), 'instance not found')) {
            return true;
        }

        return false;
    }

    /**
     * Update the message status in the database.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function updateMessageStatus(string $status, array $metadata = []): void
    {
        ChatMessage::query()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $this->messageId)
            ->update([
                'status' => $status,
                'metadata' => array_merge($this->metadata, $metadata, [
                    'last_status_update' => now()->toIso8601String(),
                ]),
            ]);
    }

    /**
     * Handle errors and update status accordingly.
     */
    private function handleError(\Throwable $exception): void
    {
        Log::warning('[SendWhatsAppMessageJob] Send attempt failed', [
            'message_id' => $this->messageId,
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
            'error' => $exception->getMessage(),
        ]);

        $this->updateMessageStatus('pending', [
            'last_error' => $exception->getMessage(),
            'last_attempt' => now()->toIso8601String(),
            'attempt_count' => $this->attempts(),
        ]);
    }

    /**
     * Mask phone number for logging.
     */
    private function maskPhoneNumber(string $phone): string
    {
        if (strlen($phone) < 8) {
            return '***';
        }

        return substr($phone, 0, 4).'****'.substr($phone, -2);
    }
}
