<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Actions\Rag\DeleteDocumentAction;
use Domain\Ai\Actions\Rag\GetKnowledgeStatsAction;
use Domain\Ai\Actions\Rag\GetRagStatsAction;
use Domain\Ai\Actions\Rag\IngestUrlAction;
use Domain\Ai\Actions\Rag\ReindexDocumentAction;
use Domain\Ai\Actions\Rag\UploadDocumentAction;
use Domain\Ai\Contracts\AiRagServiceInterface;
use Domain\Ai\DTOs\KnowledgeSearchFiltersDTO;
use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Enums\AiRagSearchModeEnum;
use Domain\Ai\Exceptions\StorageLimitExceededException;
use Domain\Ai\Http\Requests\AiKnowledgeBulkDeleteRequest;
use Domain\Ai\Http\Requests\AiKnowledgeBulkReindexRequest;
use Domain\Ai\Http\Requests\AiKnowledgeUrlIngestRequest;
use Domain\Ai\Http\Requests\SearchKnowledgeRequest;
use Domain\Ai\Http\Requests\UploadKnowledgeRequest;
use Domain\Ai\Http\Resources\KnowledgeChunkResource;
use Domain\Ai\Http\Resources\KnowledgeDocumentResource;
use Domain\Ai\Http\Resources\KnowledgeSearchResultResource;
use Domain\Ai\Http\Resources\KnowledgeStatsResource;
use Domain\Ai\Http\Resources\RagStatsResource;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

/**
 * Controller for AI Knowledge Base (RAG) endpoints.
 */
final class AiKnowledgeController extends BaseController
{
    private const CACHE_TTL_MINUTES = 5;

    /**
     * List tenant's knowledge documents.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('ai.autopilots.manage');
        $tenantId = $this->tenantId($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $search = trim((string) $request->input('search', ''));
        $cacheKey = $this->documentsCacheKey($tenantId, $page, $perPage, $search);

        $documents = Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($tenantId, $search, $perPage) {
                $query = AiKnowledgeDocument::query()
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true);

                if ($search !== '') {
                    $query->where('name', 'ILIKE', SearchSanitizer::likeContains($search));
                }

                return $query
                    ->orderByDesc('created_at')
                    ->paginate($perPage);
            }
        );

        $this->rememberListCacheKey($tenantId, $cacheKey);

        return KnowledgeDocumentResource::collection($documents);
    }

    /**
     * Upload a new knowledge document.
     */
    public function store(
        UploadKnowledgeRequest $request,
        UploadDocumentAction $action,
    ): JsonResponse {
        $this->authorize('ai.autopilots.manage');
        $tenantId = $this->tenantId($request);
        $tenant = PlatformTenant::findOrFail($tenantId);

        $file = $request->file('file');
        if (! $file) {
            return response()->json([
                'message' => 'No file uploaded.',
            ], 422);
        }

        try {
            $document = $action->execute(
                tenant: $tenant,
                file: $file,
                name: $request->input('name'),
            );

            $this->forgetListCache($tenantId);

            return response()->json([
                'message' => 'Document uploaded and queued for processing.',
                'data' => new KnowledgeDocumentResource($document),
            ], 202);
        } catch (StorageLimitExceededException $e) {
            return $e->render($request);
        }
    }

    /**
     * Get a specific document.
     */
    public function show(Request $request, string $id): KnowledgeDocumentResource|JsonResponse
    {
        $this->authorize('ai.autopilots.manage');
        $tenantId = $this->tenantId($request);
        $cacheKey = $this->documentCacheKey($tenantId, $id);

        $document = Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => AiKnowledgeDocument::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $id)
                ->where('is_active', true)
                ->first()
        );

        if (! $document) {
            Cache::forget($cacheKey);

            return response()->json([
                'message' => 'Document not found.',
            ], 404);
        }

        return new KnowledgeDocumentResource($document);
    }

    /**
     * Delete a knowledge document.
     */
    public function destroy(
        Request $request,
        string $id,
        DeleteDocumentAction $action,
    ): JsonResponse {
        $this->authorize('ai.autopilots.manage');
        $tenantId = $this->tenantId($request);

        $document = AiKnowledgeDocument::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if (! $document) {
            return response()->json([
                'message' => 'Document not found.',
            ], 404);
        }

        $action->execute($document);

        $this->forgetDocumentCache($tenantId, $id);
        $this->forgetListCache($tenantId);

        return response()->json([
            'message' => 'Document deleted successfully.',
        ]);
    }

    /**
     * Reindex a document.
     */
    public function reindex(
        Request $request,
        string $id,
        ReindexDocumentAction $action,
    ): JsonResponse {
        $this->authorize('ai.autopilots.manage');
        $tenantId = $this->tenantId($request);

        $document = AiKnowledgeDocument::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if (! $document) {
            return response()->json([
                'message' => 'Document not found.',
            ], 404);
        }

        if (! $document->canReprocess()) {
            return response()->json([
                'message' => 'Document is currently being processed.',
            ], 422);
        }

        $document = $action->execute($document);

        $this->forgetDocumentCache($tenantId, $id);
        $this->forgetListCache($tenantId);

        return response()->json([
            'message' => 'Document queued for reindexing.',
            'data' => new KnowledgeDocumentResource($document),
        ], 202);
    }

    /**
     * Get knowledge base statistics.
     */
    public function stats(Request $request, GetKnowledgeStatsAction $action): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');
        $tenantId = $this->tenantId($request);
        $tenant = PlatformTenant::findOrFail($tenantId);

        $stats = $action->execute($tenant);

        return response()->json([
            'success' => true,
            'data' => new KnowledgeStatsResource($stats),
        ]);
    }

    /**
     * Search knowledge base.
     */
    public function search(
        SearchKnowledgeRequest $request,
        AiRagServiceInterface $ragService,
    ): JsonResponse {
        $this->authorize('ai.autopilots.manage');
        $tenantId = $this->tenantId($request);

        $query = $request->input('query');
        $limit = $request->input('limit', 5);
        $minScore = (float) $request->input('min_score', 0.30);
        $mode = AiRagSearchModeEnum::from((string) $request->input('mode', AiRagSearchModeEnum::VECTOR->value));

        $filters = $this->buildSearchFilters($request);

        $results = $ragService->search($query, $tenantId, $limit, $minScore, $mode, $filters);

        return response()->json([
            'success' => true,
            'data' => KnowledgeSearchResultResource::collection($results),
        ]);
    }

    /**
     * Build search filters DTO from request.
     */
    private function buildSearchFilters(SearchKnowledgeRequest $request): ?KnowledgeSearchFiltersDTO
    {
        $documentIds = $request->input('document_ids');
        $fileTypesInput = $request->input('file_types');
        $createdAfter = $request->input('created_after');
        $createdBefore = $request->input('created_before');

        if ($documentIds === null && $fileTypesInput === null && $createdAfter === null && $createdBefore === null) {
            return null;
        }

        $fileTypes = null;
        if ($fileTypesInput !== null) {
            $fileTypes = array_filter(array_map(
                static fn (string $value): ?AiDocumentType => AiDocumentType::tryFrom($value),
                $fileTypesInput
            ));
        }

        return new KnowledgeSearchFiltersDTO(
            documentIds: $documentIds !== null ? array_values($documentIds) : null,
            fileTypes: $fileTypes !== null && $fileTypes !== [] ? array_values($fileTypes) : null,
            createdAfter: $createdAfter !== null ? \Illuminate\Support\Carbon::parse($createdAfter) : null,
            createdBefore: $createdBefore !== null ? \Illuminate\Support\Carbon::parse($createdBefore) : null,
        );
    }

    /**
     * Get RAG query statistics.
     */
    public function ragStats(
        Request $request,
        GetRagStatsAction $action,
    ): JsonResponse {
        $this->authorize('ai.autopilots.manage');
        $tenantId = $this->tenantId($request);

        $days = max(1, min(90, (int) $request->input('days', 7)));

        $stats = $action->execute($tenantId, $days);

        return response()->json([
            'success' => true,
            'data' => new RagStatsResource($stats),
        ]);
    }

    /**
     * Ingest a public URL into knowledge base.
     */
    public function ingestUrl(
        AiKnowledgeUrlIngestRequest $request,
        IngestUrlAction $action,
    ): JsonResponse {
        $this->authorize('ai.autopilots.manage');
        $tenantId = $this->tenantId($request);
        $tenant = PlatformTenant::findOrFail($tenantId);

        try {
            $document = $action->execute(
                tenant: $tenant,
                url: (string) $request->input('url'),
                title: (string) $request->input('title'),
            );

            $this->forgetListCache($tenantId);

            return response()->json([
                'message' => 'URL queued for processing.',
                'data' => new KnowledgeDocumentResource($document),
            ], 202);
        } catch (StorageLimitExceededException $e) {
            return $e->render($request);
        }
    }

    /**
     * Bulk delete knowledge documents.
     */
    public function bulkDelete(
        AiKnowledgeBulkDeleteRequest $request,
        DeleteDocumentAction $action,
    ): JsonResponse {
        $this->authorize('ai.autopilots.manage');
        $tenantId = $this->tenantId($request);
        $ids = $request->input('ids', []);

        $documents = AiKnowledgeDocument::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->get();

        foreach ($documents as $document) {
            $action->execute($document);
            $this->forgetDocumentCache($tenantId, (string) $document->id);
        }

        $this->forgetListCache($tenantId);

        return response()->json([
            'message' => 'Bulk delete completed.',
            'deleted_count' => $documents->count(),
        ]);
    }

    /**
     * Bulk reindex knowledge documents.
     */
    public function bulkReindex(
        AiKnowledgeBulkReindexRequest $request,
        ReindexDocumentAction $action,
    ): JsonResponse {
        $this->authorize('ai.autopilots.manage');
        $tenantId = $this->tenantId($request);
        $ids = $request->input('ids', []);

        $documents = AiKnowledgeDocument::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->get();

        $queuedCount = 0;
        foreach ($documents as $document) {
            if (! $document->canReprocess()) {
                continue;
            }

            $action->execute($document);
            $this->forgetDocumentCache($tenantId, (string) $document->id);
            $queuedCount++;
        }

        $this->forgetListCache($tenantId);

        return response()->json([
            'message' => 'Bulk reindex queued.',
            'queued_count' => $queuedCount,
        ], 202);
    }

    /**
     * List chunks for a specific document.
     */
    public function chunks(Request $request, string $id): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('ai.autopilots.manage');
        $tenantId = $this->tenantId($request);
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));

        $document = AiKnowledgeDocument::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if (! $document) {
            return response()->json([
                'message' => 'Document not found.',
            ], 404);
        }

        $chunks = AiKnowledgeChunk::query()
            ->where('tenant_id', $tenantId)
            ->where('document_id', $id)
            ->orderBy('chunk_index')
            ->paginate($perPage);

        return KnowledgeChunkResource::collection($chunks);
    }

    private function documentsCacheKey(string $tenantId, int $page, int $perPage, string $search): string
    {
        $searchKey = $search !== '' ? md5(mb_strtolower($search)) : 'none';

        return "ai.knowledge.documents.{$tenantId}.page.{$page}.per_page.{$perPage}.search.{$searchKey}";
    }

    private function documentCacheKey(string $tenantId, string $documentId): string
    {
        return "ai.knowledge.document.{$tenantId}.{$documentId}";
    }

    private function listCacheIndexKey(string $tenantId): string
    {
        return "ai.knowledge.documents.keys.{$tenantId}";
    }

    private function rememberListCacheKey(string $tenantId, string $cacheKey): void
    {
        $indexKey = $this->listCacheIndexKey($tenantId);
        $keys = Cache::get($indexKey, []);

        if (! in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            Cache::forever($indexKey, $keys);
        }
    }

    private function forgetListCache(string $tenantId): void
    {
        $indexKey = $this->listCacheIndexKey($tenantId);
        $keys = Cache::pull($indexKey, []);

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    private function forgetDocumentCache(string $tenantId, string $documentId): void
    {
        Cache::forget($this->documentCacheKey($tenantId, $documentId));
    }
}
