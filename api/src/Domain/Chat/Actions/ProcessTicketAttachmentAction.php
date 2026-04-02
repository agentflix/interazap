<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Processar anexos de tickets (áudio, mídia, documentos).
 *
 * Responsabilidades:
 * - Validar tipo e tamanho de arquivo
 * - Armazenar arquivo em storage seguro
 * - Registrar metadata de mídia
 * - Criar registro de mensagem com anexo
 */
final class ProcessTicketAttachmentAction
{
    private const MAX_FILE_SIZE_MB = 100;

    private const ALLOWED_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'audio/mpeg',
        'audio/wav',
        'video/mp4',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Processar arquivo anexado ao ticket.
     *
     * @param  array<string, mixed>  $metadata  Metadados opcionais (mimetype, filename, etc)
     * @return ChatMessage Mensagem criada com anexo
     *
     * @throws \InvalidArgumentException Se arquivo for inválido
     */
    public function processAttachment(
        string $tenantId,
        string $ticketId,
        UploadedFile $file,
        array $metadata = []
    ): ChatMessage {
        $this->validateFile($file);

        $ticket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($ticketId);

        $storagePath = $this->storeFile($file, $tenantId, $ticketId);

        $fileMetadata = [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'storage_path' => $storagePath,
            ...$metadata,
        ];

        $message = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenantId,
            'ticket_id' => (string) $ticketId,
            'contact_id' => $ticket->contact_id,
            'content' => $fileMetadata['original_name'],
            'type' => $this->detectMessageType($fileMetadata['mime_type']),
            'direction' => 'incoming',
            'is_from_contact' => true,
            'source' => 'webhook',
            'status' => 'received',
            'metadata' => $fileMetadata,
        ]);

        Log::info('chat.attachment_processed', [
            'tenant_id' => $tenantId,
            'ticket_id' => $ticketId,
            'message_id' => $message->id,
            'storage_path' => $storagePath,
            'mime_type' => $fileMetadata['mime_type'],
            'size_bytes' => $fileMetadata['size_bytes'],
        ]);

        return $message;
    }

    /**
     * Validar arquivo antes de processar.
     *
     *
     * @throws \InvalidArgumentException
     */
    private function validateFile(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();
        if (! in_array($mimeType, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException("Tipo de arquivo não permitido: {$mimeType}");
        }

        $sizeMb = $file->getSize() / (1024 * 1024);
        if ($sizeMb > self::MAX_FILE_SIZE_MB) {
            throw new \InvalidArgumentException('Arquivo excede tamanho máximo de '.self::MAX_FILE_SIZE_MB.'MB');
        }
    }

    /**
     * Armazenar arquivo em storage seguro.
     *
     * @return string Caminho relativo no storage
     */
    private function storeFile(UploadedFile $file, string $tenantId, string $ticketId): string
    {
        $disk = Storage::disk('private');
        $path = "chat/{$tenantId}/attachments/{$ticketId}";

        $fileName = Str::orderedUuid().'.'.$file->getClientOriginalExtension();

        return $disk->putFileAs($path, $file, $fileName);
    }

    /**
     * Detectar tipo de mensagem baseado em MIME type.
     *
     * @return string Tipo de mensagem (image, audio, video, document, file)
     */
    private function detectMessageType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'audio/') => 'audio',
            str_starts_with($mimeType, 'video/') => 'video',
            $mimeType === 'application/pdf' => 'document',
            default => 'file',
        };
    }
}
