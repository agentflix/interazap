<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO for Knowledge Base statistics.
 *
 * @readonly
 */
final readonly class KnowledgeStatsDTO
{
    /**
     * @param  int  $documentCount  Total number of documents
     * @param  int  $totalStorageBytes  Total storage used in bytes
     * @param  int  $storageLimitBytes  Storage limit from plan
     * @param  float  $storageUsedPercent  Percentage of storage used
     * @param  int  $totalChunks  Total number of chunks
     * @param  int  $documentsReady  Documents with READY status
     * @param  int  $documentsProcessing  Documents with PROCESSING status
     * @param  int  $documentsPending  Documents with PENDING status
     * @param  int  $documentsFailed  Documents with FAILED status
     */
    public function __construct(
        public int $documentCount,
        public int $totalStorageBytes,
        public int $storageLimitBytes,
        public float $storageUsedPercent,
        public int $totalChunks,
        public int $documentsReady,
        public int $documentsProcessing,
        public int $documentsPending = 0,
        public int $documentsFailed = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'document_count' => $this->documentCount,
            'total_storage_bytes' => $this->totalStorageBytes,
            'storage_limit_bytes' => $this->storageLimitBytes,
            'storage_used_percent' => round($this->storageUsedPercent, 2),
            'total_chunks' => $this->totalChunks,
            'documents_ready' => $this->documentsReady,
            'documents_processing' => $this->documentsProcessing,
            'documents_pending' => $this->documentsPending,
            'documents_failed' => $this->documentsFailed,
        ];
    }
}
