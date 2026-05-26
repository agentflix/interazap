<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Rag;

use Domain\Ai\Contracts\AiStorageLimitServiceInterface;
use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Exceptions\StorageLimitExceededException;
use Domain\Ai\Jobs\AiKnowledgeProcessJob;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Action para upload e criação de documentos de conhecimento.
 *
 * Gerencia armazenamento do arquivo, versionamento (desativa versão anterior
 * quando o nome já existe) e despacho do job de processamento.
 */
final class UploadDocumentAction
{
    public function __construct(
        private readonly AiStorageLimitServiceInterface $storageLimitService,
    ) {}

    /**
     * Faz upload, cria o documento e enfileira o processamento.
     *
     * Se já existir um documento ativo com o mesmo nome, o anterior é
     * desativado e o novo recebe a versão incrementada.
     *
     * @param  PlatformTenant  $tenant  Tenant dono do documento.
     * @param  UploadedFile  $file  Arquivo enviado pelo usuário.
     * @param  string|null  $name  Nome opcional; usa o nome do arquivo se omitido.
     * @return AiKnowledgeDocument Documento criado com status PENDING.
     *
     * @throws StorageLimitExceededException Se o tenant não tiver espaço suficiente.
     * @throws \InvalidArgumentException Se o tipo de arquivo não for suportado.
     */
    public function execute(
        PlatformTenant $tenant,
        UploadedFile $file,
        ?string $name = null,
    ): AiKnowledgeDocument {
        $fileSize = (int) $file->getSize();

        // Check storage limit
        if (! $this->storageLimitService->canUpload($tenant, $fileSize)) {
            throw new StorageLimitExceededException(
                currentUsage: $this->storageLimitService->getCurrentUsage($tenant),
                limit: $this->storageLimitService->getStorageLimit($tenant),
                requestedSize: $fileSize,
            );
        }

        // Determine file type
        $extension = strtolower($file->getClientOriginalExtension());
        $fileType = AiDocumentType::fromExtension($extension);

        if (! $fileType) {
            throw new \InvalidArgumentException("Unsupported file type: {$extension}");
        }

        // Determine name
        $documentName = $name ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        // Check for existing document with same name (versioning)
        $existingDocument = AiKnowledgeDocument::query()
            ->where('tenant_id', $tenant->id)
            ->where('name', $documentName)
            ->where('is_active', true)
            ->first();

        // Store file
        $storagePath = $this->storeFile($tenant, $file);

        // Create new document
        $document = AiKnowledgeDocument::create([
            'tenant_id' => $tenant->id,
            'name' => $documentName,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $storagePath,
            'file_size_bytes' => $fileSize,
            'file_type' => $fileType,
            'version' => $existingDocument ? $existingDocument->version + 1 : 1,
            'embedding_status' => AiEmbeddingStatus::PENDING,
            'is_active' => true,
        ]);

        // Handle versioning - mark old document as inactive
        if ($existingDocument) {
            $existingDocument->update([
                'replaced_by' => $document->id,
                'is_active' => false,
            ]);
        }

        // Dispatch processing job
        AiKnowledgeProcessJob::dispatch($document->id, (string) $document->tenant_id);

        return $document;
    }

    /**
     * Armazena o arquivo em caminho isolado por tenant e UUID único.
     *
     * @param  PlatformTenant  $tenant  Tenant dono do arquivo.
     * @param  UploadedFile  $file  Arquivo a armazenar.
     * @return string Caminho relativo no Storage.
     */
    private function storeFile(PlatformTenant $tenant, UploadedFile $file): string
    {
        $uuid = (string) Str::orderedUuid();
        $filename = $file->getClientOriginalName();

        // Store in tenant-specific path
        $path = "knowledge/{$tenant->id}/{$uuid}/{$filename}";

        Storage::put($path, file_get_contents($file->getPathname()));

        return $path;
    }
}
