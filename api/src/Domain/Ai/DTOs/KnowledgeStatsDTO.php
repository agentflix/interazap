<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO para estatísticas consolidadas da base de conhecimento do tenant.
 *
 * @readonly
 */
final readonly class KnowledgeStatsDTO
{
    /**
     * @param  int  $documentCount  Total de documentos ativos.
     * @param  int  $totalStorageBytes  Armazenamento utilizado em bytes.
     * @param  int  $storageLimitBytes  Limite de armazenamento do plano em bytes.
     * @param  float  $storageUsedPercent  Percentual de armazenamento utilizado.
     * @param  int  $totalChunks  Total de chunks gerados.
     * @param  int  $documentsReady  Documentos com status READY.
     * @param  int  $documentsProcessing  Documentos com status PROCESSING.
     * @param  int  $documentsPending  Documentos com status PENDING.
     * @param  int  $documentsFailed  Documentos com status FAILED.
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
     * Converte para array para serialização na resposta HTTP.
     *
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
