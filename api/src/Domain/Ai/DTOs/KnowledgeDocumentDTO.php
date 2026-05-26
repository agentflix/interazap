<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * DTO para dados de documento da base de conhecimento.
 *
 * @readonly
 */
final readonly class KnowledgeDocumentDTO
{
    /**
     * @param  string|null  $id  UUID do documento.
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $name  Nome exibido do documento.
     * @param  string  $originalFilename  Nome original do arquivo enviado.
     * @param  string  $filePath  Caminho no Storage.
     * @param  int  $fileSizeBytes  Tamanho do arquivo em bytes.
     * @param  AiDocumentType  $fileType  Tipo do documento (enum).
     * @param  int  $version  Número de versão do documento.
     * @param  string|null  $replacedBy  UUID do documento substituto (versionamento).
     * @param  int  $chunkCount  Quantidade de chunks gerados.
     * @param  AiEmbeddingStatus  $embeddingStatus  Status de processamento.
     * @param  string|null  $errorMessage  Mensagem de erro em caso de falha.
     * @param  array<string, mixed>|null  $metadata  Metadados adicionais.
     * @param  bool  $isActive  Indica se o documento está ativo.
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
     * Cria o DTO a partir de um request HTTP com arquivo enviado.
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
