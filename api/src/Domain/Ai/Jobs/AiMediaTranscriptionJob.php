<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Domain\Ai\Enums\AiMediaTranscriptionStatus;
use Domain\Ai\Services\AiMediaTranscriptionService;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Job assíncrono de transcrição de mídia.
 *
 * Processa transcrição de áudio (Whisper), descrição de imagem (Vision)
 * ou transcrição de vídeo (FFmpeg + Whisper + Vision), salvando o
 * resultado na mensagem e registrando o uso no AiUsageLog.
 *
 * @category Jobs
 */
final class AiMediaTranscriptionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public int $timeout = 120;

    /**
     * @param  string  $messageId  UUID da mensagem.
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $mediaType  Tipo de mídia: audio, image, video.
     * @param  string  $filePath  Caminho do arquivo no storage.
     * @param  string|null  $mimeType  MIME type do arquivo.
     */
    public function __construct(
        private readonly string $messageId,
        private readonly string $tenantId,
        private readonly string $mediaType,
        private readonly string $filePath,
        private readonly ?string $mimeType = null,
    ) {
        $this->onQueue((string) config('ai.transcription.queue_name', 'ai-transcription'));
    }

    /**
     * Executar a transcrição da mídia.
     */
    public function handle(AiMediaTranscriptionService $service, ChatBroadcastService $broadcast): void
    {
        logger()->info('[AiMediaTranscriptionJob] Iniciando transcrição', [
            'message_id' => $this->messageId,
            'tenant_id' => $this->tenantId,
            'media_type' => $this->mediaType,
            'mime_type' => $this->mimeType,
        ]);

        $message = ChatMessage::find($this->messageId);
        if (! $message) {
            logger()->warning('[AiMediaTranscriptionJob] Mensagem não encontrada', [
                'message_id' => $this->messageId,
            ]);

            return;
        }

        $message->loadMissing('extended');

        // Verificar se já foi transcrita
        if ($message->media_transcription_status === AiMediaTranscriptionStatus::COMPLETED->value) {
            logger()->debug('[AiMediaTranscriptionJob] Mensagem já transcrita', [
                'message_id' => $this->messageId,
            ]);

            return;
        }

        // Marcar como processando
        $message->update([
            'media_transcription_status' => AiMediaTranscriptionStatus::PROCESSING->value,
        ]);

        try {
            // Resolver caminho absoluto do arquivo
            $absolutePath = $this->resolveAbsolutePath();
            if ($absolutePath === null || ! file_exists($absolutePath)) {
                logger()->error('[AiMediaTranscriptionJob] Arquivo não encontrado', [
                    'message_id' => $this->messageId,
                    'file_path' => $this->filePath,
                ]);
                $this->markFailed($message);

                return;
            }

            // Verificar se o tenant ainda tem transcrição habilitada
            $tenant = PlatformTenant::find($this->tenantId);
            if (! $tenant || ! $service->shouldTranscribe($tenant, $this->mediaType)) {
                logger()->info('[AiMediaTranscriptionJob] Transcrição desabilitada para este tipo', [
                    'message_id' => $this->messageId,
                    'media_type' => $this->mediaType,
                ]);
                $this->markFailed($message);

                return;
            }

            // Executar transcrição conforme tipo
            $result = match ($this->mediaType) {
                'audio' => $service->transcribeAudio($absolutePath),
                'image' => $service->describeImage($absolutePath),
                'video' => $service->transcribeVideo($absolutePath, $tenant->media_transcription_video_max_seconds),
                default => null,
            };

            if ($result === null || ($result->transcription === null || $result->transcription === '')) {
                logger()->warning('[AiMediaTranscriptionJob] Transcrição vazia ou tipo não suportado', [
                    'message_id' => $this->messageId,
                    'media_type' => $this->mediaType,
                ]);
                $this->markFailed($message);

                return;
            }

            // Atualizar mensagem com resultado
            $message->update([
                'media_transcription' => $result->transcription,
                'media_transcription_provider' => $result->provider,
                'media_transcription_status' => AiMediaTranscriptionStatus::COMPLETED->value,
                'media_transcription_tokens' => $result->inputTokens + $result->outputTokens,
                'media_transcription_cost' => $result->cost,
                'media_transcribed_at' => now(),
            ]);

            // Registrar uso em ai_usage_logs
            $result = new \Domain\Ai\DTOs\AiMediaTranscriptionDTO(
                messageId: $this->messageId,
                tenantId: $this->tenantId,
                mediaType: $result->mediaType,
                filePath: $result->filePath,
                transcription: $result->transcription,
                provider: $result->provider,
                modelName: $result->modelName,
                inputTokens: $result->inputTokens,
                outputTokens: $result->outputTokens,
                cost: $result->cost,
                latencyMs: $result->latencyMs,
                durationSeconds: $result->durationSeconds,
                fileSizeKb: $result->fileSizeKb,
            );
            $service->logUsage($result, $message);

            // Notificar frontend sobre transcrição concluída
            $broadcast->emitMessageStatus([
                'message_id' => $this->messageId,
                'ticket_id' => (string) $message->ticket_id,
                'tenant_id' => $this->tenantId,
                'status' => (string) $message->status,
                'media_transcription_status' => AiMediaTranscriptionStatus::COMPLETED->value,
                'media_transcription' => $result->transcription,
            ]);

            logger()->info('[AiMediaTranscriptionJob] Transcrição concluída', [
                'message_id' => $this->messageId,
                'media_type' => $this->mediaType,
                'tokens' => $result->inputTokens + $result->outputTokens,
                'cost' => $result->cost,
                'latency_ms' => $result->latencyMs,
            ]);
        } catch (\Throwable $e) {
            logger()->error('[AiMediaTranscriptionJob] Erro na transcrição', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);

            $this->markFailed($message);

            // Re-throw para retry do job
            throw $e;
        }
    }

    /**
     * Resolver caminho absoluto do arquivo de mídia.
     */
    private function resolveAbsolutePath(): ?string
    {
        // Se já é absoluto
        if (str_starts_with($this->filePath, '/')) {
            return $this->filePath;
        }

        // Tentar storage public
        $disk = Storage::disk('public');
        if ($disk->exists($this->filePath)) {
            return $disk->path($this->filePath);
        }

        // Tentar extrair path relativo de URL
        $storagePath = str_replace('/storage/', '', $this->filePath);
        if ($disk->exists($storagePath)) {
            return $disk->path($storagePath);
        }

        return null;
    }

    /**
     * Marcar mensagem como falha na transcrição.
     */
    private function markFailed(ChatMessage $message): void
    {
        $message->update([
            'media_transcription_status' => AiMediaTranscriptionStatus::FAILED->value,
        ]);
    }
}
