<?php

declare(strict_types=1);

namespace Domain\Chat\Jobs;

use Domain\Ai\Jobs\AiMediaTranscriptionJob;
use Domain\Ai\Services\AiMediaTranscriptionService;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Job assíncrono para download de arquivos de mídia.
 *
 * Responsável por baixar mídia de URLs externas (WhatsApp/UazAPI),
 * persistir no Storage local, atualizar o registro da mensagem e
 * notificar o frontend via broadcast sobre a disponibilidade do arquivo.
 *
 * @category Jobs
 */
final class ChatMediaDownloadJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Prevent duplicate media downloads.
     */
    public int $uniqueFor = 120;

    /**
     * @param  string  $messageId  UUID da mensagem no banco.
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $instanceToken  Token da instância UazAPI.
     * @param  string  $externalMessageId  ID externo da mensagem (messageid do WhatsApp).
     * @param  string|null  $originalUrl  URL original da mídia (pode ser criptografada).
     * @param  string|null  $mimeType  MIME type conhecido ou null.
     */
    public function __construct(
        private readonly string $messageId,
        private readonly string $tenantId,
        private readonly string $instanceToken,
        private readonly string $externalMessageId,
        private readonly ?string $originalUrl = null,
        private readonly ?string $mimeType = null,
    ) {
        $this->onQueue('media');
    }

    /**
     * Unique ID based on message ID.
     */
    public function uniqueId(): string
    {
        return $this->messageId;
    }

    /**
     * Executa o download, persistência e atualização da mensagem.
     */
    public function handle(ChatGatewayService $gateway, ChatBroadcastService $broadcast): void
    {
        logger()->info('[ChatMediaDownloadJob] Iniciando download de mídia', [
            'message_id' => $this->messageId,
            'external_message_id' => $this->externalMessageId,
            'original_url' => $this->originalUrl,
        ]);

        $message = ChatMessage::find($this->messageId);
        if (! $message) {
            logger()->warning('[ChatMediaDownloadJob] Mensagem não encontrada', [
                'message_id' => $this->messageId,
            ]);

            return;
        }

        // Se já tem um arquivo local, não precisa baixar novamente
        if ($message->file_url && ! $this->isExternalUrl((string) $message->file_url)) {
            logger()->debug('[ChatMediaDownloadJob] Mídia já está local', [
                'message_id' => $this->messageId,
                'file_url' => $message->file_url,
            ]);

            return;
        }

        try {
            // 1. Solicitar URL descriptografada à UazAPI
            $downloadResult = $gateway->downloadMedia($this->instanceToken, $this->externalMessageId);

            $publicUrl = $downloadResult['fileURL']
                ?? $downloadResult['fileUrl']
                ?? null;
            $base64Data = $downloadResult['base64Data']
                ?? $downloadResult['base64']
                ?? null;
            $resolvedMimeType = $downloadResult['mimetype'] ?? $this->mimeType ?? 'application/octet-stream';

            if (! $publicUrl && ! $base64Data) {
                logger()->warning('[ChatMediaDownloadJob] UazAPI não retornou URL/base64 de download', [
                    'message_id' => $this->messageId,
                    'result' => $downloadResult,
                ]);

                return;
            }

            // 2. Validar MIME type para segurança
            if (! $this->isAllowedMimeType($resolvedMimeType)) {
                logger()->warning('[ChatMediaDownloadJob] MIME type não permitido', [
                    'message_id' => $this->messageId,
                    'mime_type' => $resolvedMimeType,
                ]);

                return;
            }

            // 3. Obter conteúdo via base64 ou HTTP
            if ($base64Data) {
                $fileContent = base64_decode((string) $base64Data, true);
                if ($fileContent === false || $fileContent === '') {
                    logger()->error('[ChatMediaDownloadJob] Base64 inválido ou vazio', [
                        'message_id' => $this->messageId,
                    ]);
                    $this->fail(new \RuntimeException('Base64 inválido'));

                    return;
                }
            } else {
                /** @var Response $response */
                $response = Http::withOptions(['stream' => true])
                    ->timeout(60)
                    ->get($publicUrl);

                if (! $response->successful()) {
                    logger()->error('[ChatMediaDownloadJob] Falha no download HTTP', [
                        'message_id' => $this->messageId,
                        'status' => $response->status(),
                        'url' => $publicUrl,
                    ]);
                    $this->fail(new \RuntimeException('Download HTTP falhou: '.$response->status()));

                    return;
                }

                $fileContent = $response->body();
                if (empty($fileContent)) {
                    logger()->error('[ChatMediaDownloadJob] Conteúdo vazio no download', [
                        'message_id' => $this->messageId,
                    ]);
                    $this->fail(new \RuntimeException('Conteúdo do arquivo vazio'));

                    return;
                }
            }

            // 4. Determinar extensão e caminho de storage
            $extension = $this->getExtensionFromMimeType($resolvedMimeType);
            $date = now()->format('Y/m/d');
            $fileName = $this->externalMessageId.'.'.$extension;
            $path = "chat/media/{$this->tenantId}/{$date}/{$fileName}";

            // 5. Persistir no Storage (disco public)
            Storage::disk('public')->put($path, $fileContent);

            // 6. Construir URL local
            $localUrl = Storage::url($path);
            if (! str_starts_with($localUrl, 'http')) {
                $localUrl = rtrim((string) config('app.url'), '/').$localUrl;
            }

            // 7. Atualizar registro da mensagem
            $message->loadMissing('extended');
            $message->file_url = $localUrl;
            $message->file_name = $fileName;
            $message->mime_type = $resolvedMimeType;
            $message->file_size = strlen($fileContent);
            $message->save();

            logger()->info('[ChatMediaDownloadJob] Mídia salva com sucesso', [
                'message_id' => $this->messageId,
                'local_url' => $localUrl,
                'path' => $path,
                'size' => strlen($fileContent),
            ]);

            // 8. Notificar frontend sobre atualização da mensagem
            $broadcast->emitMessageStatus([
                'message_id' => $this->messageId,
                'ticket_id' => (string) $message->ticket_id,
                'tenant_id' => $this->tenantId,
                'status' => (string) $message->status,
                'file_url' => $localUrl,
                'file_name' => $fileName,
                'mime_type' => $resolvedMimeType,
                'file_size' => strlen($fileContent),
            ]);

            // 9. Despachar transcrição de mídia se habilitada no tenant
            $this->dispatchTranscriptionIfEnabled($message, $path, $resolvedMimeType);
        } catch (\Throwable $e) {
            logger()->error('[ChatMediaDownloadJob] Erro no processamento de mídia', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw para permitir retry automático
            throw $e;
        }
    }

    /**
     * Despachar job de transcrição de mídia se o tenant tiver a feature habilitada.
     */
    private function dispatchTranscriptionIfEnabled(ChatMessage $message, string $storagePath, string $mimeType): void
    {
        $transcriptionService = app(AiMediaTranscriptionService::class);
        $mediaType = $transcriptionService->resolveMediaType($mimeType);
        if ($mediaType === null) {
            return;
        }

        $tenant = PlatformTenant::find($this->tenantId);
        if (! $tenant || ! $transcriptionService->shouldTranscribe($tenant, $mediaType)) {
            return;
        }

        logger()->info('[ChatMediaDownloadJob] Despachando transcrição de mídia', [
            'message_id' => $this->messageId,
            'media_type' => $mediaType,
        ]);

        AiMediaTranscriptionJob::dispatch(
            $this->messageId,
            $this->tenantId,
            $mediaType,
            $storagePath,
            $mimeType,
        );
    }

    /**
     * Verifica se é uma URL externa (não local).
     */
    private function isExternalUrl(string $url): bool
    {
        $appUrl = (string) config('app.url');

        return ! str_starts_with($url, $appUrl) && ! str_starts_with($url, '/storage');
    }

    /**
     * Valida se o MIME type é permitido para upload.
     */
    private function isAllowedMimeType(string $mimeType): bool
    {
        // Strip parameters like "; codecs=opus" for validation
        $baseMime = trim(explode(';', $mimeType)[0]);

        $allowed = [
            // Imagens
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            // Vídeos
            'video/mp4',
            'video/webm',
            'video/quicktime',
            'video/3gpp',
            // Áudio
            'audio/mpeg',
            'audio/mp3',
            'audio/ogg',
            'audio/wav',
            'audio/webm',
            'audio/aac',
            'audio/m4a',
            // Documentos
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            // Outros comuns do WhatsApp
            'application/octet-stream',
        ];

        // Verificar match exato ou prefixo genérico
        foreach ($allowed as $allowedType) {
            if ($baseMime === $allowedType) {
                return true;
            }
        }

        // Permitir tipos genéricos de imagem/video/audio/text
        $genericPrefixes = ['image/', 'video/', 'audio/', 'text/'];
        foreach ($genericPrefixes as $prefix) {
            if (str_starts_with($baseMime, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtém extensão de arquivo a partir do MIME type.
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        // Strip parameters like "; codecs=opus" for lookup
        $baseMime = trim(explode(';', $mimeType)[0]);

        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/3gpp' => '3gp',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'audio/webm' => 'weba',
            'audio/aac' => 'aac',
            'audio/m4a' => 'm4a',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
        ];

        return $map[$baseMime] ?? $map[$mimeType] ?? 'bin';
    }
}
