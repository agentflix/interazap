<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Gerencia as chaves e estratégia de invalidação de cache para documentos da Base de Conhecimento.
 *
 * Contexto: centraliza todas as interações com o Cache que anteriormente estavam dispersas
 * no AiKnowledgeController, tornando o contrato de caching testável isoladamente.
 * Mantém um índice de chaves por tenant para permitir invalidação em massa de listas paginadas.
 */
final class AiKnowledgeCacheService
{
    /** TTL in minutes for document and document-list entries. */
    private const int CACHE_TTL_MINUTES = 5;

    /**
     * Retorna a lista de documentos em cache ou carrega via $loader e armazena o resultado.
     *
     * A chave gerada é rastreada no índice do tenant para permitir invalidação em massa.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  int  $page  Número da página atual.
     * @param  int  $perPage  Itens por página.
     * @param  string  $search  Termo de busca opcional.
     * @param  Closure  $loader  Callable que busca o paginador no banco de dados.
     */
    public function rememberList(string $tenantId, int $page, int $perPage, string $search, Closure $loader): mixed
    {
        $cacheKey = $this->documentsCacheKey($tenantId, $page, $perPage, $search);
        $result = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), $loader);
        $this->trackListCacheKey($tenantId, $cacheKey);

        return $result;
    }

    /**
     * Retorna o documento em cache ou carrega via $loader e armazena o resultado.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $documentId  UUID do documento.
     * @param  Closure  $loader  Callable que busca o documento no banco de dados.
     */
    public function rememberDocument(string $tenantId, string $documentId, Closure $loader): mixed
    {
        $cacheKey = $this->documentCacheKey($tenantId, $documentId);

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), $loader);
    }

    /**
     * Invalida a entrada em cache de um documento específico.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $documentId  UUID do documento.
     */
    public function forgetDocument(string $tenantId, string $documentId): void
    {
        Cache::forget($this->documentCacheKey($tenantId, $documentId));
    }

    /**
     * Invalida todas as páginas de lista em cache para o tenant informado.
     *
     * Utiliza o índice de chaves armazenado para remover cada entrada individualmente.
     *
     * @param  string  $tenantId  UUID do tenant.
     */
    public function forgetList(string $tenantId): void
    {
        $indexKey = $this->listCacheIndexKey($tenantId);
        $keys = Cache::pull($indexKey, []);

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /** Gera a chave de cache para uma página de lista de documentos. */
    private function documentsCacheKey(string $tenantId, int $page, int $perPage, string $search): string
    {
        $searchKey = $search !== '' ? md5(mb_strtolower($search)) : 'none';

        return "ai.knowledge.documents.{$tenantId}.page.{$page}.per_page.{$perPage}.search.{$searchKey}";
    }

    /** Gera a chave de cache para um documento individual. */
    private function documentCacheKey(string $tenantId, string $documentId): string
    {
        return "ai.knowledge.document.{$tenantId}.{$documentId}";
    }

    /** Gera a chave do índice que rastreia todas as chaves de lista do tenant. */
    private function listCacheIndexKey(string $tenantId): string
    {
        return "ai.knowledge.documents.keys.{$tenantId}";
    }

    /** Registra uma chave de cache de lista no índice do tenant para invalidação futura. */
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
