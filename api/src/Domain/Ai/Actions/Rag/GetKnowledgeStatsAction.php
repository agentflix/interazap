<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Rag;

use Domain\Ai\Contracts\AiStorageLimitServiceInterface;
use Domain\Ai\DTOs\KnowledgeStatsDTO;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;

/**
 * Action for getting knowledge base statistics.
 */
final class GetKnowledgeStatsAction
{
    public function __construct(
        private readonly AiStorageLimitServiceInterface $storageLimitService,
    ) {}

    /**
     * Get statistics for tenant's knowledge base.
     */
    public function execute(PlatformTenant $tenant): KnowledgeStatsDTO
    {
        // Get document counts by status
        $statusCounts = AiKnowledgeDocument::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->selectRaw('embedding_status, COUNT(*) as count')
            ->groupBy('embedding_status')
            ->pluck('count', 'embedding_status')
            ->toArray();

        // Total documents
        $documentCount = array_sum($statusCounts);

        // Get status-specific counts
        $documentsReady = $statusCounts[AiEmbeddingStatus::READY->value] ?? 0;
        $documentsProcessing = $statusCounts[AiEmbeddingStatus::PROCESSING->value] ?? 0;
        $documentsPending = $statusCounts[AiEmbeddingStatus::PENDING->value] ?? 0;
        $documentsFailed = $statusCounts[AiEmbeddingStatus::FAILED->value] ?? 0;

        // Get storage usage
        $totalStorageBytes = $this->storageLimitService->getCurrentUsage($tenant);
        $storageLimitBytes = $this->storageLimitService->getStorageLimit($tenant);
        $storageUsedPercent = $storageLimitBytes > 0
            ? ($totalStorageBytes / $storageLimitBytes) * 100
            : 0.0;

        // Get total chunks
        $totalChunks = AiKnowledgeChunk::query()
            ->where('tenant_id', $tenant->id)
            ->count();

        return new KnowledgeStatsDTO(
            documentCount: $documentCount,
            totalStorageBytes: $totalStorageBytes,
            storageLimitBytes: $storageLimitBytes,
            storageUsedPercent: $storageUsedPercent,
            totalChunks: $totalChunks,
            documentsReady: $documentsReady,
            documentsProcessing: $documentsProcessing,
            documentsPending: $documentsPending,
            documentsFailed: $documentsFailed,
        );
    }
}
