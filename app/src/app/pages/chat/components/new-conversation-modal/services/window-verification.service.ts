import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable, of } from 'rxjs';
import { catchError, map, tap } from 'rxjs/operators';
import { environment } from '@env/environment';
import { type WindowStatus, type WindowStatusResponse } from 'src/app/core/models/window-status.model';

interface CacheEntry {
  status: WindowStatus;
  timestamp: number;
}

const CACHE_STALE_TIME_MS = 30_000; // 30 seconds

/**
 * Serviço de verificação do status da janela de 24 horas de mensagens para contatos.
 *
 * @remarks
 * Determina se um contato pode receber mensagens de texto livre ou se
 * exige mensagens baseadas em template ao usar a API WhatsApp Business da Meta.
 * Utiliza cache com 30 segundos de expiração para evitar chamadas redundantes à API.
 */
@Injectable({ providedIn: 'root' })
export class WindowVerificationService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/chat/contacts`;
  private readonly cache = new Map<string, CacheEntry>();

  /**
   * Verifica o status da janela de 24 horas para um contato.
   *
   * @param contactId - ID do contato a verificar.
   * @returns Observable com o status da janela.
   *
   * @remarks
   * Retorna valor em cache (30s) se disponível, caso contrário consulta a API.
   * Em caso de erro, retorna status padrão que força o modo template.
   */
  checkStatus(contactId: string): Observable<WindowStatus> {
    const cached = this.getCached(contactId);
    if (cached) {
      return of(cached);
    }

    return this.http.get<WindowStatusResponse>(`${this.baseUrl}/${contactId}/window-status`).pipe(
      map((response) => response.data),
      tap((status) => {
        this.setCache(contactId, status);
      }),
      catchError(() => {
        // On error, return a default status that forces template mode
        const fallback: WindowStatus = {
          canSendFreeText: false,
          lastMessageAt: null,
        };
        return of(fallback);
      }),
    );
  }

  /**
   * Invalida o cache de status para um contato específico.
   * Chamar ao enviar ou receber uma nova mensagem.
   *
   * @param contactId - ID do contato cujo cache deve ser invalidado.
   */
  invalidateCache(contactId: string): void {
    this.cache.delete(contactId);
  }

  /** Limpa todos os status de janela em cache. */
  clearCache(): void {
    this.cache.clear();
  }

  private getCached(contactId: string): WindowStatus | null {
    const entry = this.cache.get(contactId);
    if (!entry) {
      return null;
    }

    const isStale = Date.now() - entry.timestamp > CACHE_STALE_TIME_MS;
    if (isStale) {
      this.cache.delete(contactId);
      return null;
    }

    return entry.status;
  }

  private setCache(contactId: string, status: WindowStatus): void {
    this.cache.set(contactId, {
      status,
      timestamp: Date.now(),
    });
  }
}
