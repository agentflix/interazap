import { Injectable, inject } from '@angular/core';

interface CacheEntry<T> {
  data: T;
  timestamp: number;
  ttl: number;
}

/**
 * Servico de cache generico com TTL para operacoes offline.
 * Armazena dados no localStorage com tempo de expiracao configuravel.
 * TTL padrao: 7 dias.
 *
 * @class OfflineCacheService
 * @example
 * ```ts
 * const cache = inject(OfflineCacheService);
 * cache.set('my-data', { foo: 'bar' }, 60000); // 1 minuto
 * const data = cache.get<{ foo: string }>('my-data');
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class OfflineCacheService {
  private storage = localStorage;
  private readonly DEFAULT_TTL = 7 * 24 * 60 * 60 * 1000; // 7 days

  /**
   * Armazena um valor no cache com TTL (time-to-live).
   *
   * @param key - Chave unica de identificacao
   * @param data - Valor a ser armazenado (qualquer tipo serializavel)
   * @param ttl - Tempo de vida em milissegundos (default: 7 dias)
   */
  set<T>(key: string, data: T, ttl: number = this.DEFAULT_TTL): void {
    const entry: CacheEntry<T> = {
      data,
      timestamp: Date.now(),
      ttl,
    };
    this.storage.setItem(`cache:${key}`, JSON.stringify(entry));
  }

  /**
   * Recupera um valor do cache. Retorna null se inexistente ou expirado.
   *
   * @param key - Chave de busca
   * @returns O valor armazenado ou null se nao encontrado/expirado
   */
  get<T>(key: string): T | null {
    const raw = this.storage.getItem(`cache:${key}`);
    if (!raw) return null;

    try {
      const entry: CacheEntry<T> = JSON.parse(raw);
      const now = Date.now();
      const expiresAt = entry.timestamp + entry.ttl;

      if (now > expiresAt) {
        this.remove(key);
        return null;
      }

      return entry.data;
    } catch {
      this.remove(key);
      return null;
    }
  }

  /**
   * Remove um item especifico do cache.
   *
   * @param key - Chave do item a ser removido
   */
  remove(key: string): void {
    this.storage.removeItem(`cache:${key}`);
  }

  /**
   * Remove todos os itens do cache gerenciados por este servico.
   */
  clear(): void {
    const keysToRemove: string[] = [];
    for (let i = 0; i < this.storage.length; i++) {
      const key = this.storage.key(i);
      if (key?.startsWith('cache:')) {
        keysToRemove.push(key);
      }
    }
    keysToRemove.forEach((key) => this.storage.removeItem(key));
  }

  /**
   * Verifica se uma chave existe no cache e nao esta expirada.
   *
   * @param key - Chave a ser verificada
   * @returns True se a chave existe e e valida
   */
  has(key: string): boolean {
    return this.get(key) !== null;
  }
}
