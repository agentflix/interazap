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
 * Job assíncrono de envio de mensagens WhatsApp com retry e rate limiting.
 *
 * Gerencia a entrega assíncrona de mensagens utilizando backoff exponencial
 * e limitação de taxa para respeitar os limites da API WhatsApp.
 */
final class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RetryableWithBackoff;
    use SerializesModels;

    /** Identificador do tenant proprietário da mensagem. */
    private readonly string $tenantId;

    /**
     * Intervalos de backoff específicos para erros transientes da API WhatsApp.
     *
     * @return array<int, int>
     */
    protected function getBackoffDelays(): array
    {
        return [5, 15, 45, 120, 300];
    }

    /**
     * @param  string  $messageId  ID da mensagem no banco de dados.
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $instanceId  ID da instância WhatsApp de envio.
     * @param  string  $to  Número de telefone do destinatário.
     * @param  string  $content  Conteúdo da mensagem.
     * @param  string  $type  Tipo da mensagem (text, image, document, etc.).
     * @param  array<string, mixed>  $metadata  Metadados adicionais.
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
     * Middlewares pelo qual o job deve passar (ex.: rate limiting por instância).
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
     * Retorna ID único do job para evitar envios duplicados.
     */
    public function uniqueId(): string
    {
        return "whatsapp:{$this->messageId}";
    }

    /**
     * Tags para rastreamento e filtragem do job no painel de filas.
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
     * Envia a mensagem via gateway, atualiza o status e notifica o frontend via broadcast.
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
     * Trata a falha definitiva do job marcando a mensagem como `failed` e notificando o frontend.
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
     * Determina se a exceção é irrecuperável e deve falhar imediatamente (sem retry).
     */
    protected function shouldFailImmediately(\Throwable $exception): bool
    {
        // Não tenta novamente em números ou conteúdo inválidos
        if ($exception instanceof \InvalidArgumentException) {
            return true;
        }

        // Não tenta novamente em erros de autenticação
        if (str_contains($exception->getMessage(), 'authentication')) {
            return true;
        }

        // Não tenta novamente quando a instância não existe
        if (str_contains($exception->getMessage(), 'instance not found')) {
            return true;
        }

        return false;
    }

    /**
     * Atualiza o status da mensagem no banco de dados.
     *
     * @param  array<string, mixed>  $metadata  Dados adicionais a mesclar nos metadados.
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
     * Registra o erro de tentativa e atualiza o status da mensagem para `pending`.
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
     * Mascara o número de telefone para exibição segura em logs.
     */
    private function maskPhoneNumber(string $phone): string
    {
        if (strlen($phone) < 8) {
            return '***';
        }

        return substr($phone, 0, 4).'****'.substr($phone, -2);
    }
}
