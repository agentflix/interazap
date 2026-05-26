<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Domain\Ai\Contracts\AiProviderInterface;
use Domain\Ai\DTOs\AiCompletionDTO;
use Domain\Ai\Enums\AiProviderType;
use Domain\Ai\Models\AiRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Shared\Jobs\Middleware\RateLimitedJob;
use Shared\Jobs\Traits\RetryableWithBackoff;

/**
 * Job para processamento de respostas de IA com timeout estendido e retry de erros transitórios.
 *
 * Executa completions assíncronas via AiProviderInterface com padrões de retry
 * conservadores para erros transitórios da API do LLM (rate limits, timeouts, etc.).
 * Erros permanentes (chave inválida, política de conteúdo, modelo inválido)
 * falham imediatamente sem retry.
 */
final class ProcessAIResponseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RetryableWithBackoff;
    use SerializesModels;

    /**
     * Identificador do tenant.
     */
    private readonly string $tenantId;

    /**
     * Retorna os intervalos de backoff progressivos para retry.
     * Mais conservadores que o padrão para respeitar rate limits da API de IA.
     *
     * @return array<int, int>
     */
    protected function getBackoffDelays(): array
    {
        return [15, 45, 120, 300, 600];
    }

    /**
     * Cria nova instância do job.
     *
     * @param  string  $runId  UUID do run de IA.
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $prompt  Prompt a ser processado.
     * @param  AiProviderType  $provider  Provedor de IA a ser utilizado.
     * @param  array<string, mixed>  $options  Opções adicionais (model, temperature, etc.).
     * @param  array<string, mixed>  $context  Dados de contexto para a resposta.
     */
    public function __construct(
        private readonly string $runId,
        string $tenantId,
        private readonly string $prompt,
        private readonly AiProviderType $provider = AiProviderType::OPENAI,
        private readonly array $options = [],
        private readonly array $context = [],
    ) {
        $this->tenantId = $tenantId;
        // Override trait defaults for AI-specific configuration
        $this->timeout = 180;
        $this->maxExceptions = 3;
    }

    /**
     * Middlewares do job (rate limiting específico para IA).
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            RateLimitedJob::forAI(),
        ];
    }

    /**
     * Retorna o ID único do job para prevenir duplicação.
     */
    public function uniqueId(): string
    {
        return "ai-run:{$this->runId}";
    }

    /**
     * Retorna as tags do job para monitoramento no Horizon.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'ai',
            "tenant:{$this->tenantId}",
            "provider:{$this->provider->value}",
            'ai:completion',
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(AiProviderInterface $aiProvider): void
    {
        Log::info('[ProcessAIResponseJob] Processing AI request', [
            'run_id' => $this->runId,
            'tenant_id' => $this->tenantId,
            'provider' => $this->provider->value,
            'prompt_length' => strlen($this->prompt),
        ]);

        $this->updateRunStatus('processing');

        try {
            $completion = $aiProvider->complete(new AiCompletionDTO(
                prompt: $this->prompt,
                model: $this->options['model'] ?? null,
                temperature: (float) ($this->options['temperature'] ?? 0.7),
                maxTokens: (int) ($this->options['max_tokens'] ?? 2048),
                context: $this->context,
            ));

            $this->updateRunStatus('completed', [
                'output' => $completion->content,
                'tokens_used' => $completion->tokensUsed,
                'model' => $completion->model,
                'finish_reason' => $completion->finishReason,
            ]);

            Log::info('[ProcessAIResponseJob] AI request completed', [
                'run_id' => $this->runId,
                'tokens_used' => $completion->tokensUsed,
                'model' => $completion->model,
            ]);
        } catch (\Throwable $e) {
            if ($this->shouldFailImmediately($e)) {
                Log::error('[ProcessAIResponseJob] Non-retryable error', [
                    'run_id' => $this->runId,
                    'error' => $e->getMessage(),
                ]);
                $this->fail($e);

                return;
            }

            $this->handleTransientError($e);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[ProcessAIResponseJob] Job failed permanently', [
            'run_id' => $this->runId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->updateRunStatus('failed', [
            'error' => $exception->getMessage(),
            'failed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Verifica se a exceção é permanente e deve falhar imediatamente (sem retry).
     */
    protected function shouldFailImmediately(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        // Don't retry on invalid API key
        if (str_contains($message, 'invalid api key') || str_contains($message, 'authentication')) {
            return true;
        }

        // Don't retry on content policy violations
        if (str_contains($message, 'content policy') || str_contains($message, 'moderation')) {
            return true;
        }

        // Don't retry on invalid model
        if (str_contains($message, 'model not found') || str_contains($message, 'invalid model')) {
            return true;
        }

        // Don't retry on context length exceeded (needs prompt reduction)
        if (str_contains($message, 'context length') || str_contains($message, 'max_tokens')) {
            return true;
        }

        // Invalid arguments
        if ($exception instanceof \InvalidArgumentException) {
            return true;
        }

        return false;
    }

    /**
     * Verifica se o erro é transitório (rate limit, timeout, erro de servidor).
     */
    private function isTransientError(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        $transientPatterns = [
            'rate limit',
            'rate_limit',
            'too many requests',
            '429',
            'timeout',
            'timed out',
            'connection reset',
            'connection refused',
            'server error',
            '500',
            '502',
            '503',
            '504',
            'temporarily unavailable',
            'overloaded',
        ];

        foreach ($transientPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lida com erros transitórios, atualizando o status do run e logando o aviso.
     */
    private function handleTransientError(\Throwable $exception): void
    {
        Log::warning('[ProcessAIResponseJob] Transient error, will retry', [
            'run_id' => $this->runId,
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
            'error' => $exception->getMessage(),
            'is_transient' => $this->isTransientError($exception),
        ]);

        $this->updateRunStatus('retrying', [
            'last_error' => $exception->getMessage(),
            'last_attempt' => now()->toIso8601String(),
            'attempt_count' => $this->attempts(),
        ]);
    }

    /**
     * Atualiza o status do run de IA no banco de dados.
     *
     * @param  array<string, mixed>  $data  Dados adicionais a mesclar (output, tokens, erro, etc.).
     */
    private function updateRunStatus(string $status, array $data = []): void
    {
        AiRun::query()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $this->runId)
            ->update(array_merge([
                'status' => $status,
                'updated_at' => now(),
            ], $data));
    }
}
