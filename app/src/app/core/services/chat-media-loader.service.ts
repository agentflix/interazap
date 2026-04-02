import { Injectable, inject } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { type Observable, of } from 'rxjs';
import { catchError, finalize, map, shareReplay, tap } from 'rxjs/operators';
import { CalledMessageService } from './called-message.service';

@Injectable({ providedIn: 'root' })
export class ChatMediaLoaderService {
  private readonly messageService = inject(CalledMessageService);
  private static readonly CACHE_TTL_MS = 25 * 60 * 1000;
  private readonly cache = new Map<string, { url: string; cachedAt: number }>();
  private readonly inflight = new Map<string, Observable<string>>();
  private readonly notFound = new Set<string>();
  private readonly failedUntil = new Map<string, number>();

  private static readonly TRANSIENT_FAILURE_COOLDOWN_MS = 10000;

  /**
   * Carrega a URL de midia para uma mensagem de chat.
   * Implementa cache em memoria (TTL 25min), deduplicacao de requisicoes
   * e cooldown para falhas transitórias.
   *
   * @param calledId - Identificador da chamada/sessao de chat
   * @param messageId - Identificador da mensagem
   * @returns Observable com a URL da midia ou string vazia se indisponivel
   */
  load(calledId: string | number, messageId: string | number): Observable<string> {
    const key = this.getKey(calledId, messageId);

    if (this.notFound.has(key)) {
      return of('');
    }

    const blockedUntil = this.failedUntil.get(key);
    if (blockedUntil !== undefined && blockedUntil > Date.now()) {
      return of('');
    }

    const cached = this.cache.get(key);
    if (cached) {
      if (Date.now() - cached.cachedAt < ChatMediaLoaderService.CACHE_TTL_MS) {
        return of(cached.url);
      }
      this.cache.delete(key);
    }

    const pending = this.inflight.get(key);
    if (pending) {
      return pending;
    }

    const request$ = this.messageService.getMediaUrl(String(calledId), String(messageId)).pipe(
      map((response) => response?.data?.url?.trim() ?? ''),
      tap((url) => {
        if (url) {
          this.cache.set(key, { url, cachedAt: Date.now() });
          this.failedUntil.delete(key);
        }
      }),
      catchError((error: unknown) => {
        if (error instanceof HttpErrorResponse && error.status === 404) {
          this.notFound.add(key);
        } else {
          this.failedUntil.set(
            key,
            Date.now() + ChatMediaLoaderService.TRANSIENT_FAILURE_COOLDOWN_MS,
          );
        }
        return of('');
      }),
      shareReplay({ bufferSize: 1, refCount: true }),
      finalize(() => this.inflight.delete(key)),
    );

    this.inflight.set(key, request$);

    return request$;
  }

  /**
   * Pre-popula o cache com uma URL conhecida (ex: via preview).
   * Util para evitar requisicoes redundantes quando a URL ja esta disponivel.
   *
   * @param calledId - Identificador da chamada
   * @param messageId - Identificador da mensagem
   * @param url - URL valida da midia
   */
  prime(
    calledId: string | number,
    messageId: string | number,
    url: string | null | undefined,
  ): void {
    if (!url) return;
    this.cache.set(this.getKey(calledId, messageId), { url, cachedAt: Date.now() });
  }

  private getKey(calledId: string | number, messageId: string | number): string {
    return `${calledId}:${messageId}`;
  }
}
