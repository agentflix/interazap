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
 * Action for uploading and creating knowledge documents.
 *
 * Handles file storage, versioning, and processing dispatch.
 */
final class UploadDocumentAction
{
    public function __construct(
        private readonly AiStorageLimitServiceInterface $storageLimitService,
    ) {}

    /**
     * Upload and create a knowledge document.
     *
     * @throws StorageLimitExceededException
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
        AiKnowledgeProcessJob::dispatch($document->id);

        return $document;
    }

    /**
     * Store the uploaded file.
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
