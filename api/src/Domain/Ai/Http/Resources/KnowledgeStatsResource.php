<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Ai\DTOs\KnowledgeStatsDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para serialização de estatísticas da base de conhecimento.
 *
 * @mixin KnowledgeStatsDTO
 */
final class KnowledgeStatsResource extends JsonResource
{
    /**
     * Transforma o recurso em array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var KnowledgeStatsDTO $dto */
        $dto = $this->resource;

        return [
            'document_count' => $dto->documentCount,
            'total_storage_bytes' => $dto->totalStorageBytes,
            'storage_limit_bytes' => $dto->storageLimitBytes,
            'storage_used_percent' => round($dto->storageUsedPercent, 2),
            'storage_formatted' => $this->formatBytes($dto->totalStorageBytes),
            'storage_limit_formatted' => $dto->storageLimitBytes > 0
                ? $this->formatBytes($dto->storageLimitBytes)
                : 'Unlimited',
            'total_chunks' => $dto->totalChunks,
            'documents_ready' => $dto->documentsReady,
            'documents_processing' => $dto->documentsProcessing,
            'documents_pending' => $dto->documentsPending,
            'documents_failed' => $dto->documentsFailed,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return round($value, 2).' '.$units[$unitIndex];
    }
}
