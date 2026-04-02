<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * DTO for Knowledge Document data.
 *
 * @readonly
 */
final readonly class KnowledgeDocumentDTO
{
    /**
     * @param  string|null  $id  Document ID
     * @param  string  $tenantId  Tenant ID
     * @param  string  $name  Display name
     * @param  string  $originalFilename  Original file name
     * @param  string  $filePath  Storage path
     * @param  int  $fileSizeBytes  File size in bytes
     * @param  AiDocumentType  $fileType  File type enum
     * @param  int  $version  Version number
     * @param  string|null  $replacedBy  ID of replacing document
     * @param  int  $chunkCount  Number of chunks
     * @param  AiEmbeddingStatus  $embeddingStatus  Processing status
     * @param  string|null  $errorMessage  Error message if failed
     * @param  array<string, mixed>|null  $metadata  Additional metadata
     * @param  bool  $isActive  Active flag
     */
    public function __construct(
        public ?string $id,
        public string $tenantId,
        public string $name,
        public string $originalFilename,
        public string $filePath,
        public int $fileSizeBytes,
        public AiDocumentType $fileType,
        public int $version = 1,
        public ?string $replacedBy = null,
        public int $chunkCount = 0,
        public AiEmbeddingStatus $embeddingStatus = AiEmbeddingStatus::PENDING,
        public ?string $errorMessage = null,
        public ?array $metadata = null,
        public bool $isActive = true,
    ) {}

    /**
     * Create from request with uploaded file.
     */
    public static function fromUpload(
        Request $request,
        string $tenantId,
        UploadedFile $file,
        string $storedPath,
    ): self {
        $extension = strtolower($file->getClientOriginalExtension());
        $fileType = AiDocumentType::fromExtension($extension) ?? AiDocumentType::TXT;

        return new self(
            id: null,
            tenantId: $tenantId,
            name: $request->input('name', $file->getClientOriginalName()),
            originalFilename: $file->getClientOriginalName(),
            filePath: $storedPath,
            fileSizeBytes: (int) $file->getSize(),
            fileType: $fileType,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'original_filename' => $this->originalFilename,
            'file_path' => $this->filePath,
            'file_size_bytes' => $this->fileSizeBytes,
            'file_type' => $this->fileType->value,
            'version' => $this->version,
            'replaced_by' => $this->replacedBy,
            'chunk_count' => $this->chunkCount,
            'embedding_status' => $this->embeddingStatus->value,
            'error_message' => $this->errorMessage,
            'metadata' => $this->metadata,
            'is_active' => $this->isActive,
        ];
    }
}
