<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Manages the cache keys and invalidation strategy for AI Knowledge Base documents.
 *
 * Centralises all Cache interactions that were previously spread across
 * AiKnowledgeController, making the caching contract testable in isolation.
 */
final class AiKnowledgeCacheService
{
    /** TTL in minutes for document and document-list entries. */
    private const int CACHE_TTL_MINUTES = 5;

    /**
     * Return cached list of documents, or load via $loader and cache the result.
     *
     * @param  string  $tenantId  Tenant UUID.
     * @param  int  $page  Current page number.
     * @param  int  $perPage  Items per page.
     * @param  string  $search  Optional search term.
     * @param  Closure  $loader  Callable that fetches the paginator from the DB.
     */
    public function rememberList(string $tenantId, int $page, int $perPage, string $search, Closure $loader): mixed
    {
        $cacheKey = $this->documentsCacheKey($tenantId, $page, $perPage, $search);
        $result = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), $loader);
        $this->trackListCacheKey($tenantId, $cacheKey);

        return $result;
    }

    /**
     * Return cached document, or load via $loader and cache the result.
     *
     * @param  string  $tenantId  Tenant UUID.
     * @param  string  $documentId  Document UUID.
     * @param  Closure  $loader  Callable that fetches the document from the DB.
     */
    public function rememberDocument(string $tenantId, string $documentId, Closure $loader): mixed
    {
        $cacheKey = $this->documentCacheKey($tenantId, $documentId);

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), $loader);
    }

    /**
     * Invalidate the cached entry for a specific document.
     *
     * @param  string  $tenantId  Tenant UUID.
     * @param  string  $documentId  Document UUID.
     */
    public function forgetDocument(string $tenantId, string $documentId): void
    {
        Cache::forget($this->documentCacheKey($tenantId, $documentId));
    }

    /**
     * Invalidate all cached list pages for the given tenant.
     *
     * @param  string  $tenantId  Tenant UUID.
     */
    public function forgetList(string $tenantId): void
    {
        $indexKey = $this->listCacheIndexKey($tenantId);
        $keys = Cache::pull($indexKey, []);

        foreach ($keys as $key) {
            Cache::forget($key);
        }
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

    private function trackListCacheKey(string $tenantId, string $cacheKey): void
    {
        $indexKey = $this->listCacheIndexKey($tenantId);
        $keys = Cache::get($indexKey, []);

        if (! in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            Cache::forever($indexKey, $keys);
        }
    }
}
