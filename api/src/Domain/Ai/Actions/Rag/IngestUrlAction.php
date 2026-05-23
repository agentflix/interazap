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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Action for ingesting a URL into AI knowledge base.
 */
final class IngestUrlAction
{
    public function __construct(
        private readonly AiStorageLimitServiceInterface $storageLimitService,
    ) {}

    /**
     * Download URL content, store, and dispatch processing.
     *
     * @throws StorageLimitExceededException
     */
    public function execute(PlatformTenant $tenant, string $url, string $title): AiKnowledgeDocument
    {
        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf('Could not fetch URL content (HTTP %d).', $response->status()));
        }

        $rawHtml = (string) $response->body();
        $fileSize = strlen($rawHtml);

        if (! $this->storageLimitService->canUpload($tenant, $fileSize)) {
            throw new StorageLimitExceededException(
                currentUsage: $this->storageLimitService->getCurrentUsage($tenant),
                limit: $this->storageLimitService->getStorageLimit($tenant),
                requestedSize: $fileSize,
            );
        }

        $uuid = (string) Str::orderedUuid();
        $filePath = sprintf('knowledge/%s/%s/source.html', $tenant->id, $uuid);
        Storage::put($filePath, $rawHtml);

        $document = AiKnowledgeDocument::create([
            'tenant_id' => $tenant->id,
            'name' => $title,
            'original_filename' => parse_url($url, PHP_URL_HOST) ?: 'url-source',
            'file_path' => $filePath,
            'file_size_bytes' => $fileSize,
            'file_type' => AiDocumentType::URL,
            'version' => 1,
            'embedding_status' => AiEmbeddingStatus::PENDING,
            'metadata' => ['source_url' => $url],
            'is_active' => true,
        ]);

        AiKnowledgeProcessJob::dispatch($document->id, (string) $document->tenant_id);

        return $document;
    }
}
